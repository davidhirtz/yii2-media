<?php

declare(strict_types=1);

namespace Hirtz\Media\Models;

use davidhirtz\yii2\datetime\DateTime;
use davidhirtz\yii2\datetime\DateTimeBehavior;
use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\Queries\FileQuery;
use Hirtz\Media\Models\Queries\FolderQuery;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Behaviors\BlameableBehavior;
use Hirtz\Skeleton\Behaviors\TimestampBehavior;
use Hirtz\Skeleton\Behaviors\TrailBehavior;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Models\Interfaces\AdminRouteInterface;
use Hirtz\Skeleton\Models\Interfaces\TrailModelInterface;
use Hirtz\Skeleton\Models\Interfaces\TypeAttributeInterface;
use Hirtz\Skeleton\Models\Traits\TrailModelTrait;
use Hirtz\Skeleton\Models\Traits\TypeAttributeTrait;
use Hirtz\Skeleton\Models\Traits\UpdatedByUserTrait;
use Hirtz\Skeleton\Validators\DynamicRangeValidator;
use Hirtz\Skeleton\Validators\UniqueValidator;
use Override;
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
class Folder extends ActiveRecord implements AdminRouteInterface, TypeAttributeInterface, TrailModelInterface
{
    use ModuleTrait;
    use TrailModelTrait;
    use TypeAttributeTrait;
    use UpdatedByUserTrait;

    final public const string AUTH_FOLDER_CREATE = 'folderCreate';
    final public const string AUTH_FOLDER_DELETE = 'folderDelete';
    final public const string AUTH_FOLDER_ORDER = 'folderOrder';
    final public const string AUTH_FOLDER_UPDATE = 'folderUpdate';

    public const int TYPE_DEFAULT = 1;

    public const int PATH_MAX_LENGTH = 250;
    public const string PATH_REGEX = '/^[\d\w\-_]*$/i';

    #[Override]
    public function behaviors(): array
    {
        return [
            ...parent::behaviors(),
            'DateTimeBehavior' => DateTimeBehavior::class,
            'TrailBehavior' => TrailBehavior::class,
        ];
    }

    #[Override]
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

    #[Override]
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
        if (
            !$this->getIsNewRecord()
            && $this->isAttributeChanged('path')
            && !static::getModule()->enableRenameFolders
        ) {
            $this->addInvalidAttributeError('path');
        }
    }

    #[Override]
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

    #[Override]
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

    #[Override]
    public function beforeDelete(): bool
    {
        if (!$this->isDeletable()) {
            return false;
        }

        return parent::beforeDelete();
    }

    #[Override]
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

    #[Override]
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

    public function getAdminRoute(): array
    {
        return $this->id ? ['/admin/media/folder/update', 'id' => $this->id] : ['/admin/media/folder/index'];
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
            return $this->name ?: Yii::t('skeleton', 'COMMON_MODEL_ID', [
                'model' => $this->getTrailModelType(),
                'id' => $this->id,
            ]);
        }

        return $this->getTrailModelType();
    }

    public function getTrailModelType(): string
    {
        return Yii::t('media', 'COMMON_FOLDER');
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

    #[Override]
    public function attributeLabels(): array
    {
        return [
            ...parent::attributeLabels(),
            'name' => Yii::t('skeleton', 'FOLDER_NAME_LABEL'),
            'path' => Yii::t('media', 'FOLDER_PATH_LABEL'),
            'file_count' => Yii::t('media', 'FOLDER_FILE_COUNT_LABEL'),
        ];
    }

    #[Override]
    public function formName(): string
    {
        return 'Folder';
    }

    #[Override]
    public static function tableName(): string
    {
        return '{{%folder}}';
    }
}
