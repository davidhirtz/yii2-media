<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\models;

use davidhirtz\yii2\datetime\DateTime;
use davidhirtz\yii2\datetime\DateTimeBehavior;
use davidhirtz\yii2\media\models\collections\FolderCollection;
use davidhirtz\yii2\media\models\queries\FileQuery;
use davidhirtz\yii2\media\models\queries\FolderQuery;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\behaviors\BlameableBehavior;
use davidhirtz\yii2\skeleton\behaviors\TimestampBehavior;
use davidhirtz\yii2\skeleton\behaviors\TrailBehavior;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use davidhirtz\yii2\skeleton\helpers\FileHelper;
use davidhirtz\yii2\skeleton\models\interfaces\TypeAttributeInterface;
use davidhirtz\yii2\skeleton\models\traits\TypeAttributeTrait;
use davidhirtz\yii2\skeleton\models\traits\UpdatedByUserTrait;
use davidhirtz\yii2\skeleton\validators\DynamicRangeValidator;
use davidhirtz\yii2\skeleton\validators\UniqueValidator;
use Yii;
use yii\helpers\Inflector;

/**
 * @property int $id
 * @property int $type
 * @property int $position
 * @property string $name
 * @property string $path
 * @property int $file_count
 * @property DateTime|null $updated_at
 * @property DateTime $created_at
 */
class Folder extends ActiveRecord implements TypeAttributeInterface
{
    use ModuleTrait;
    use TypeAttributeTrait;
    use UpdatedByUserTrait;

    public const AUTH_FOLDER_CREATE = 'folderCreate';
    public const AUTH_FOLDER_DELETE = 'folderDelete';
    public const AUTH_FOLDER_ORDER = 'folderOrder';
    public const AUTH_FOLDER_UPDATE = 'folderUpdate';

    public const TYPE_DEFAULT = 1;

    public const PATH_MAX_LENGTH = 250;
    public const PATH_REGEX = '/^[\d\w\-_]*$/i';

    public function behaviors(): array
    {
        return [
            ...parent::behaviors(),
            'DateTimeBehavior' => DateTimeBehavior::class,
            'TrailBehavior' => TrailBehavior::class,
        ];
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
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
                'max' => static::PATH_MAX_LENGTH,
            ],
            [
                ['path'],
                'match',
                'pattern' => static::PATH_REGEX,
            ],
            [
                ['path'],
                $this->validatePath(...),
            ],
            [
                ['path'],
                UniqueValidator::class,
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

    public function validatePath(): void
    {
        if (!$this->getIsNewRecord()
            && $this->isAttributeChanged('path')
            && !static::getModule()->enableRenameFolders) {
            $this->addInvalidAttributeError('path');
        }
    }

    public function beforeSave($insert): bool
    {
        $this->attachBehaviors([
            'BlameableBehavior' => BlameableBehavior::class,
            'TimestampBehavior' => TimestampBehavior::class,
        ]);

        if ($insert) {
            $this->position ??= static::find()->max('[[position]]') + 1;
        }

        return parent::beforeSave($insert);
    }

    public function afterSave($insert, $changedAttributes): void
    {
        if ($insert) {
            FileHelper::createDirectory($this->getUploadPath());
        } elseif (array_key_exists('path', $changedAttributes)) {
            FileHelper::rename($this->getBasePath() . $changedAttributes['path'], $this->getUploadPath());
        }

        $this->invalidateCache();

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
        $this->invalidateCache();

        parent::afterDelete();
    }

    public function getFiles(): FileQuery
    {
        /** @var FileQuery $relation */
        $relation = $this->hasMany(File::class, ['folder_id' => 'id'])
            ->indexBy('id')
            ->inverseOf('folder');

        return $relation;
    }

    public static function find(): FolderQuery
    {
        return Yii::createObject(FolderQuery::class, [static::class]);
    }

    public function invalidateCache(): void
    {
        static::getModule()->invalidatePageCache();
        FolderCollection::invalidateCache();
    }

    public function recalculateFileCount(): static
    {
        $this->file_count = $this->getFiles()->count();
        return $this;
    }

    public function getTrailAttributes(): array
    {
        return array_diff($this->attributes(), [
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
