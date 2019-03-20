<?php

namespace davidhirtz\yii2\media\models\base;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\queries\FileQuery;
use davidhirtz\yii2\media\models\queries\FolderQuery;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use davidhirtz\yii2\skeleton\models\queries\UserQuery;
use davidhirtz\yii2\skeleton\models\User;
use Yii;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;

/**
 * Class Folder.
 * @package davidhirtz\yii2\media\models\base
 *
 * @property int $id
 * @property int $type
 * @property int $parent_id
 * @property int $lft
 * @property int $rgt
 * @property int $position
 * @property string $name
 * @property string $path
 * @property integer $file_count
 * @method static \davidhirtz\yii2\media\models\Folder findOne($condition)
 */
class Folder extends ActiveRecord
{
    use ModuleTrait;

    /**
     * Constants.
     */
    const TYPE_DEFAULT = 1;

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
                ['name', 'path'],
                'filter',
                'filter' => 'trim',
            ],
            [
                ['name', 'path'],
                'string',
                'max' => 250,
            ],
            [
                ['path'],
                'match',
                'pattern' => '/^[\d\w\/\-_]*$/i'
            ],
            [
                ['path'],
                'unique',
                'skipOnError' => true,
            ],
            [
                ['type'],
                'filter',
                'filter' => 'intval',
            ],
            [
                ['type'],
                'in',
                'range' => array_keys(static::getTypes()) ?: [static::TYPE_DEFAULT],
            ],
        ];
    }

    /**
     * @return bool
     */
    public function beforeValidate()
    {
        if (!$this->type) {
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
        $this->attachBehaviors([
            'TimestampBehavior' => 'davidhirtz\yii2\skeleton\behaviors\TimestampBehavior',
            'BlameableBehavior' => 'davidhirtz\yii2\skeleton\behaviors\BlameableBehavior',
        ]);

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

        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        FileHelper::removeDirectory($this->getUploadPath());
        parent::afterDelete();
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
    public function getFiles(): FileQuery
    {
        return $this->hasMany(File::class, ['folder_id' => 'id'])
            ->indexBy('id')
            ->inverseOf('folder');
    }

    /**
     * @return FolderQuery
     */
    public static function find(): FolderQuery
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
     * @return false|int
     */
    public function recalculateFileCount()
    {
        $this->file_count = $this->getFiles()->count();
        return $this->update(false);
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
        return '/' . rtrim(static::getModule()->uploadPath, '/') . '/';
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
        return rtrim(Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . static::getModule()->uploadPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * @return array
     */
    public static function getTypes(): array
    {
        return [];
    }

    /**
     * @return string|null
     */
    public function getTypeName(): string
    {
        return static::getTypes()[$this->type]['name'];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            'name' => Yii::t('skeleton', 'Name'),
            'type' => Yii::t('skeleton', 'Type'),
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