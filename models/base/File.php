<?php

namespace davidhirtz\yii2\media\models\base;

use davidhirtz\yii2\media\models\queries\FileQuery;
use davidhirtz\yii2\datetime\DateTime;
use davidhirtz\yii2\media\modules\admin\models\forms\FolderForm;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\db\ActiveQuery;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use davidhirtz\yii2\skeleton\models\queries\UserQuery;
use davidhirtz\yii2\skeleton\models\User;
use Yii;

/**
 * Class File.
 * @package davidhirtz\yii2\media\models\base
 *
 * @property int $id
 * @property int $status
 * @property int $folder_id
 * @property string $name
 * @property string $filename
 * @property string $type
 * @property integer $width
 * @property integer $height
 * @property integer $size
 * @property int $updated_by_user_id
 * @property DateTime $updated_at
 * @property DateTime $created_at
 * @property User $updated
 * @property \davidhirtz\yii2\media\models\Folder $folder
 * @method static \davidhirtz\yii2\media\models\File findOne($condition)
 */
class File extends ActiveRecord
{
    use ModuleTrait;

    /**
     * @var array
     */
    public $allowedExtensions = ['gif', 'jpg', 'jpeg', 'png', 'webp', 'svg'];

    /**
     * Constants.
     */
    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [
                ['folder_id'],
                'validateFolderId',
            ],
            [
                ['filename'],
                'validateFilename',
            ]
        ]);
    }

    /**
     * Validates folder and populates relation.
     */
    public function validateFolderId()
    {
        if (!$this->folder) {
            $this->addInvalidAttributeError('folder_id');
        }
    }

    /**
     * Validates the filename.
     */
    public function validateFilename()
    {
        if ($this->folder) {
            if (is_file($this->folder->getUploadPath() . $this->filename)) {
                $this->addError('filename', Yii::t('media', 'A file with the name "{name}" already exists.', [
                    'name' => $this->filename,
                ]));
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate(): bool
    {
        if (!$this->folder_id) {
            $this->populateRelation('folder', FolderForm::find()
                ->where('[[parent_id]] IS NULL')
                ->orderBy(['position' => SORT_ASC])
                ->one());

            if ($this->folder) {
                $this->folder_id = $this->folder->id;
            }
        }

        return parent::beforeValidate();
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        $this->attachBehaviors([
            'TimestampBehavior' => 'davidhirtz\yii2\skeleton\behaviors\TimestampBehavior',
            'BlameableBehavior' => 'davidhirtz\yii2\skeleton\behaviors\BlameableBehavior',
        ]);

        return parent::beforeSave($insert);
    }

    /**
     * @inheritdoc
     */
    public function afterSave($insert, $changedAttributes)
    {
        if ($insert) {
            $this->folder->recalculateFileCount();
        }

        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        if ($this->folder) {
            unlink($this->folder->getUploadPath() . $this->filename);
            $this->folder->recalculateFileCount();
        }

        parent::afterDelete();
    }

    /**
     * @return ActiveQuery
     */
    public function getFolder(): ActiveQuery
    {
        return $this->hasOne(Folder::class, ['id' => 'folder_id']);
    }

    /**
     * @return UserQuery
     */
    public function getUpdated(): UserQuery
    {
        return $this->hasOne(User::class, ['id' => 'updated_by_user_id']);
    }

    /**
     * @return FileQuery
     */
    public static function find(): FileQuery
    {
        return new FileQuery(get_called_class());
    }

    /**
     * @return bool
     */
    public function hasThumbnail()
    {
        return $this->filename && in_array(pathinfo($this->filename, PATHINFO_EXTENSION), ['bmp', 'giv', 'jpg', 'jpeg', 'png', 'svg', 'webp']);
    }

    /**
     * @return array
     */
    public static function getStatuses(): array
    {
        return [
            static::STATUS_ENABLED => [
                'name' => Yii::t('skeleton', 'Enabled'),
                'icon' => 'globe',
            ],
            static::STATUS_DISABLED => [
                'name' => Yii::t('skeleton', 'Disabled'),
                'icon' => 'lock',
            ],
        ];
    }

    /**
     * @return string|null
     */
    public function getStatusName(): string
    {
        return $this->status ? static::getStatuses()[$this->status]['name'] : null;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->status == static::STATUS_ENABLED;
    }

    /**
     * @return bool
     */
    public function isDisabled()
    {
        return $this->status == static::STATUS_DISABLED;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            'slug' => Yii::t('media', 'Url'),
            'title' => Yii::t('skeleton', 'Meta title'),
            'description' => Yii::t('media', 'Meta description'),
            'section_count' => Yii::t('skeleton', 'Sections'),
            'file_count' => Yii::t('skeleton', 'Media'),
        ]);
    }

    /**
     * @return string
     */
    public function formName(): string
    {
        return 'File';
    }

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return static::getModule()->getTableName('file');
    }
}