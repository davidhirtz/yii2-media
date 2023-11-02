<?php

namespace davidhirtz\yii2\media\models;

use davidhirtz\yii2\datetime\DateTime;
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
use davidhirtz\yii2\skeleton\models\traits\UpdatedByUserTrait;
use davidhirtz\yii2\skeleton\validators\DynamicRangeValidator;
use Yii;
use yii\helpers\Inflector;

/**
 * The folder model class helps to separate files into different physical folders on local file systems and virtual
 * paths for cloud storage. Override {@link Folder} to add custom functionality.
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
 */
class Folder extends ActiveRecord
{
    use ModuleTrait;
    use TypeAttributeTrait;
    use UpdatedByUserTrait;

    public const TYPE_DEFAULT = 1;

    private static ?Folder $_default = null;

    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'TrailBehavior' => TrailBehavior::class,
        ]);
    }

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
                'pattern' => '/^[\d\w\-_]*$/i'
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
                'when' => fn() => $this->isAttributeChanged('path')
            ],
        ];
    }

    public function beforeValidate(): bool
    {
        $this->type ??= static::TYPE_DEFAULT;

        if (!$this->path) {
            $this->path = Inflector::slug($this->name);
        }

        $this->path = trim($this->path, '/');

        return parent::beforeValidate();
    }

    public function beforeSave($insert): bool
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

    public function afterSave($insert, $changedAttributes): void
    {
        if ($insert) {
            FileHelper::createDirectory($this->getUploadPath());
        } elseif (array_key_exists('path', $changedAttributes)) {
            rename($this->getBasePath() . $changedAttributes['path'], $this->getUploadPath());
        }

        static::getModule()->invalidatePageCache();

        parent::afterSave($insert, $changedAttributes);
    }

    public function beforeDelete(): bool
    {
        if (!$this->isDeletable()) {
            return false;
        }

        return parent::beforeDelete();
    }

    public function afterDelete(): void
    {
        FileHelper::removeDirectory($this->getUploadPath());
        static::getModule()->invalidatePageCache();

        parent::afterDelete();
    }

    public function getFiles(): FileQuery
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->hasMany(File::class, ['folder_id' => 'id'])
            ->indexBy('id')
            ->inverseOf('folder');
    }

    public static function find(): FolderQuery
    {
        return Yii::createObject(FolderQuery::class, [static::class]);
    }

    public function findSiblings(): FolderQuery
    {
        return static::find()->where(['parent_id' => $this->parent_id]);
    }

    public function recalculateFileCount(): static
    {
        $this->file_count = $this->getFiles()->count();
        return $this;
    }

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

    public function getTrailModelName(): string
    {
        if ($this->id) {
            return $this->name ?: Yii::t('skeleton', '{model} #{id}', [
                'model' => $this->getTrailModelType(),
                'id' => $this->id,
            ]);
        }

        return $this->getTrailModelType();
    }

    public function getTrailModelType(): string
    {
        return Yii::t('media', 'Folder');
    }

    public function getTrailModelAdminRoute(): array|false
    {
        return $this->id ? ['/admin/folder/update', 'id' => $this->id] : false;
    }

    public function getUploadUrl(): string
    {
        return $this->getBaseUrl() . rtrim($this->path, '/') . '/';
    }

    public function getBaseUrl(): string
    {
        return static::getModule()->baseUrl;
    }

    public function getUploadPath(): string
    {
        return $this->getBasePath() . rtrim($this->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public function getBasePath(): string
    {
        return static::getModule()->uploadPath;
    }

    public static function getDefault(): Folder
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
     * @return class-string
     */
    public function getActiveForm(): string
    {
        return FolderActiveForm::class;
    }

    public function isDeletable(): bool
    {
        return static::getModule()->enableDeleteNonEmptyFolders || $this->file_count <= 0;
    }

    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            'name' => Yii::t('skeleton', 'Name'),
            'path' => Yii::t('media', 'Path'),
            'file_count' => Yii::t('media', 'Files'),
        ]);
    }

    public function formName(): string
    {
        return 'Folder';
    }

    public static function tableName(): string
    {
        return static::getModule()->getTableName('folder');
    }
}