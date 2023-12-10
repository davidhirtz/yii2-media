<?php

namespace davidhirtz\yii2\media\models\base;

use davidhirtz\yii2\datetime\DateTime;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\queries\FileQuery;
use davidhirtz\yii2\media\models\queries\FolderQuery;
use davidhirtz\yii2\media\modules\admin\widgets\forms\FolderActiveForm;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\behaviors\BlameableBehavior;
use davidhirtz\yii2\skeleton\behaviors\TimestampBehavior;
use davidhirtz\yii2\skeleton\behaviors\TrailBehavior;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use davidhirtz\yii2\skeleton\db\TypeAttributeTrait;
use davidhirtz\yii2\skeleton\helpers\FileHelper;
use davidhirtz\yii2\skeleton\models\queries\UserQuery;
use davidhirtz\yii2\skeleton\models\User;
use davidhirtz\yii2\skeleton\validators\DynamicRangeValidator;
use Yii;
use yii\helpers\Inflector;

/**
 * The folder model class helps to separate files into different physical folders on local file systems and virtual
 * paths for cloud storage. Override {@link \davidhirtz\yii2\media\models\Folder} to add custom functionality.
 *
 * @property int $id
 * @property int $type
 * @property int $parent_id
 * @property int $lft
 * @property int $rgt
 * @property int $position
 * @property string $name
 * @property string $path
 * @property int $file_count
 * @property DateTime $updated_at
 * @method static \davidhirtz\yii2\media\models\Folder findOne($condition)
 */
class Folder extends ActiveRecord
{
    use ModuleTrait;
    use TypeAttributeTrait;

    /**
     * Constants.
     */
    public const TYPE_DEFAULT = 1;
    public const PATH_REGEX = '/^[\d\w\-_]*$/i';

    /**
     * @var \davidhirtz\yii2\media\models\Folder
     * @see \davidhirtz\yii2\media\models\Folder::getDefault()
     */
    private static $_default;

    /**
     * @inheritDoc
     */
    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'TrailBehavior' => TrailBehavior::class,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [
                ['name'],
                'required',
            ],
            [
                ['type'],
                DynamicRangeValidator::class,
                'skipOnEmpty' => false,
            ],
            [
                ['name', 'path'],
                'trim',
            ],
            [
                ['name', 'path'],
                'string',
                'max' => 250,
            ],
            [
                ['path'],
                'match',
                'pattern' => static::PATH_REGEX,
            ],
            [
                ['path'],
                function () {
                    if (!$this->getIsNewRecord() && $this->isAttributeChanged('path') && !static::getModule()->enableRenameFolders) {
                        $this->addInvalidAttributeError('path');
                    }
                },
            ],
            [
                ['path'],
                'unique',
                'skipOnError' => true,
                'when' => function () {
                    return $this->isAttributeChanged('path');
                }
            ],
        ];
    }

    /**
     * @return bool
     */
    public function beforeValidate()
    {
        if ($this->type === null) {
            $this->type = static::TYPE_DEFAULT;
        }

        if (!$this->path) {
            $this->path = Inflector::slug($this->name);
        }

        $this->path = trim($this->path, '/');

        return parent::beforeValidate();
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        $this->attachBehaviors(
            [
                'BlameableBehavior' => BlameableBehavior::class,
                'TimestampBehavior' => TimestampBehavior::class,
            ]
        );

        if ($insert) {
            $this->position = $this->findSiblings()->max('[[position]]') + 1;
        }

        return parent::beforeSave($insert);
    }

    /**
     * @inheritdoc
     */
    public function afterSave($insert, $changedAttributes)
    {
        if ($insert) {
            FileHelper::createDirectory($this->getUploadPath());
        } elseif (array_key_exists('path', $changedAttributes)) {
            rename($this->getBasePath() . $changedAttributes['path'], $this->getUploadPath());
        }

        static::getModule()->invalidatePageCache();

        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * @return bool
     */
    public function beforeDelete()
    {
        if (!$this->isDeletable()) {
            return false;
        }

        return parent::beforeDelete();
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        FileHelper::removeDirectory($this->getUploadPath());
        static::getModule()->invalidatePageCache();

        parent::afterDelete();
    }

    /**
     * @return UserQuery
     */
    public function getUpdated(): UserQuery
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->hasOne(User::class, ['id' => 'updated_by_user_id']);
    }

    /**
     * @return FileQuery
     */
    public function getFiles(): FileQuery
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->hasMany(File::class, ['folder_id' => 'id'])
            ->indexBy('id')
            ->inverseOf('folder');
    }

    /**
     * @return FolderQuery
     */
    public static function find()
    {
        return new FolderQuery(get_called_class());
    }

    /**
     * @return FolderQuery
     */
    public function findSiblings(): FolderQuery
    {
        return static::find()->where(['parent_id' => $this->parent_id]);
    }

    /**
     * @return $this
     */
    public function recalculateFileCount()
    {
        $this->file_count = $this->getFiles()->count();
        return $this;
    }

    /**
     * @return array
     */
    public function getTrailAttributes(): array
    {
        return array_diff($this->attributes(), [
            'lft',
            'rgt',
            'position',
            'file_count',
            'updated_by_user_id',
            'updated_at',
            'created_at',
        ]);
    }

    /**
     * @return string
     */
    public function getTrailModelName()
    {
        if ($this->id) {
            return $this->name ?: Yii::t('skeleton', '{model} #{id}', [
                'model' => $this->getTrailModelType(),
                'id' => $this->id,
            ]);
        }

        return $this->getTrailModelType();
    }

    /**
     * @return string
     */
    public function getTrailModelType(): string
    {
        return Yii::t('media', 'Folder');
    }

    /**
     * @return array|false
     */
    public function getTrailModelAdminRoute()
    {
        return $this->id ? ['/admin/folder/update', 'id' => $this->id] : false;
    }

    /**
     * @return string
     */
    public function getUploadUrl()
    {
        return $this->getBaseUrl() . rtrim($this->path, '/') . '/';
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        return static::getModule()->baseUrl;
    }

    /**
     * @return string
     */
    public function getUploadPath()
    {
        return $this->getBasePath() . rtrim($this->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * @return string
     */
    public function getBasePath()
    {
        return static::getModule()->uploadPath;
    }

    /**
     * @return \davidhirtz\yii2\media\models\Folder
     */
    public static function getDefault()
    {
        if (static::$_default === null) {
            static::$_default = static::find()
                ->where('[[parent_id]] IS NULL')
                ->orderBy(['position' => SORT_ASC])
                ->limit(1)
                ->one();

            if (!static::$_default) {
                static::$_default = new static();
                static::$_default->name = Yii::t('media', 'Default');
                static::$_default->save();
            }
        }

        return static::$_default;
    }

    /**
     * @return FolderActiveForm
     */
    public function getActiveForm()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return FolderActiveForm::class;
    }

    /**
     * @return bool
     */
    public function isDeletable(): bool
    {
        return static::getModule()->enableDeleteNonEmptyFolders || $this->file_count <= 0;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            'name' => Yii::t('skeleton', 'Name'),
            'path' => Yii::t('media', 'Path'),
            'file_count' => Yii::t('media', 'Files'),
        ]);
    }

    /**
     * @return string
     */
    public function formName(): string
    {
        return 'Folder';
    }

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return static::getModule()->getTableName('folder');
    }
}