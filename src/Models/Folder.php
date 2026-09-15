<?php

declare(strict_types=1);

namespace Hirtz\Media\Models;

use Hirtz\Media\Models\Actions\DeleteFiles;
use Hirtz\Media\Models\Actions\SaveFolderRedirects;
use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\Queries\FileQuery;
use Hirtz\Media\Models\Queries\FolderQuery;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Behaviors\BlameableBehavior;
use Hirtz\Skeleton\Behaviors\TimestampBehavior;
use Hirtz\Skeleton\Behaviors\TrailBehavior;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Models\Interfaces\SearchableInterface;
use Hirtz\Skeleton\Models\Interfaces\TrailModelInterface;
use Hirtz\Skeleton\Models\Interfaces\TypeAttributeInterface;
use Hirtz\Skeleton\Models\Traits\AdminModelTrait;
use Hirtz\Skeleton\Models\Traits\SearchableTrait;
use Hirtz\Skeleton\Models\Traits\TrailModelTrait;
use Hirtz\Skeleton\Models\Traits\TypeAttributeTrait;
use Hirtz\Skeleton\Models\Traits\UpdatedByUserTrait;
use Hirtz\Skeleton\Validators\DynamicRangeValidator;
use Hirtz\Skeleton\Validators\UniqueValidator;
use Hirtz\Skeleton\Web\User as WebUser;
use Override;
use Yii;
use davidhirtz\yii2\datetime\DateTime;
use davidhirtz\yii2\datetime\DateTimeBehavior;
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
class Folder extends ActiveRecord implements SearchableInterface, TypeAttributeInterface, TrailModelInterface
{
    use AdminModelTrait;
    use ModuleTrait;
    use SearchableTrait;
    use TrailModelTrait;
    use TypeAttributeTrait;
    use UpdatedByUserTrait;

    final public const string AUTH_FOLDER = 'folder';

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

    /**
     * @param array<string, mixed> $changedAttributes
     */
    #[Override]
    public function afterSave($insert, $changedAttributes): void
    {
        if ($insert) {
            FileHelper::createDirectory($this->getUploadPath());
        } elseif (array_key_exists('path', $changedAttributes)) {
            FileHelper::rename($this->getBasePath() . $changedAttributes['path'], $this->getUploadPath());
            SaveFolderRedirects::create($this, (string)$changedAttributes['path']);
        }

        $this->invalidateCache();

        parent::afterSave($insert, $changedAttributes);
    }

    #[Override]
    public function beforeDelete(): bool
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        if (!$this->isDeletable()) {
            $this->addError('file_count', Yii::t('media', 'FOLDER_ERROR_DELETE_NOT_EMPTY'));
            return false;
        }

        return $this->deleteFiles();
    }

    /**
     * The files are deleted through their models rather than by the foreign key's cascade, which would take the
     * rows alone and leave their assets, redirects, search documents and translations behind. The query is run
     * even for an apparently empty folder: `file_count` is denormalized, and a stale one would hand the files
     * back to the cascade.
     */
    protected function deleteFiles(): bool
    {
        $files = array_values($this->getFiles()->all());

        foreach ($files as $file) {
            $file->populateFolderRelation($this);
        }

        $failed = DeleteFiles::create($files)->getFailed();

        if ($failed) {
            $this->addError('file_count', Yii::t('media', 'FOLDER_ERROR_DELETE_FILES', [
                'count' => count($failed),
            ]));

            return false;
        }

        return true;
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

    public function getSearchAttributes(): array
    {
        return ['name', 'path'];
    }

    public function getSearchWeight(): float
    {
        return 0.4;
    }

    protected function isSearchResultVisible(): bool
    {
        return WebUser::current()?->can(static::AUTH_FOLDER) ?? false;
    }

    /**
     * @return list<string>
     */
    public function getTrailAttributes(): array
    {
        return array_values(array_diff($this->attributes(), [
            'position',
            'file_count',
            'updated_by_user_id',
            'updated_at',
            'created_at',
        ]));
    }

    public function getAdminType(): string
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
