<?php

declare(strict_types=1);

namespace Hirtz\Media\Models;

use Hirtz\Skeleton\I18n\Lang;
use davidhirtz\yii2\datetime\DateTime;
use davidhirtz\yii2\datetime\DateTimeBehavior;
use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\Interfaces\FileRelationInterface;
use Hirtz\Media\Models\Queries\FileQuery;
use Hirtz\Media\Module;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Behaviors\BlameableBehavior;
use Hirtz\Skeleton\Behaviors\RedirectBehavior;
use Hirtz\Skeleton\Behaviors\TimestampBehavior;
use Hirtz\Skeleton\Behaviors\TrailBehavior;
use Hirtz\Skeleton\Db\ActiveQuery;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Helpers\Image;
use Hirtz\Skeleton\Helpers\StringHelper;
use Hirtz\Skeleton\Models\Interfaces\DraftStatusAttributeInterface;
use Hirtz\Skeleton\Models\Interfaces\TrailModelInterface;
use Hirtz\Skeleton\Models\Traits\DraftStatusAttributeTrait;
use Hirtz\Skeleton\Models\Traits\I18nAttributesTrait;
use Hirtz\Skeleton\Models\Traits\TrailModelTrait;
use Hirtz\Skeleton\Models\Traits\UpdatedByUserTrait;
use Hirtz\Skeleton\Validators\DynamicRangeValidator;
use Hirtz\Skeleton\Validators\RelationValidator;
use Hirtz\Skeleton\Web\ChunkedUploadedFile;
use Hirtz\Skeleton\Web\StreamUploadedFile;
use Imagine\Filter\Basic\Autorotate;
use Imagine\Image\ImageInterface;
use Override;
use Yii;
use yii\base\InvalidConfigException;

/**
 * @property int $id
 * @property int $folder_id
 * @property string $name
 * @property string $basename
 * @property string $extension
 * @property int $width
 * @property int $height
 * @property int $size
 * @property string $alt_text
 * @property int $transformation_count
 * @property int|null $updated_by_user_id
 * @property DateTime|null $updated_at
 * @property DateTime $created_at
 *
 * @property-read Folder|null $folder {@see File::getFolder}
 * @property-read Transformation[] $transformations {@see File::getTransformations}
 */
class File extends ActiveRecord implements DraftStatusAttributeInterface, TrailModelInterface
{
    use I18nAttributesTrait;
    use ModuleTrait;
    use DraftStatusAttributeTrait;
    use TrailModelTrait;
    use UpdatedByUserTrait;

    final public const string AUTH_FILE_CREATE = 'fileCreate';
    final public const string AUTH_FILE_DELETE = 'fileDelete';
    final public const string AUTH_FILE_UPDATE = 'fileUpdate';

    public const int BASENAME_MAX_LENGTH = 250;

    /**
     * @var ChunkedUploadedFile|StreamUploadedFile|null the uploaded file instance
     */
    public ChunkedUploadedFile|StreamUploadedFile|null $upload = null;

    /**
     * @var int|null the maximum width for transformable image uploads, if both this and `maxHeight` are empty, the
     * image will be saved without applying transformations. If only `maxHeight` is set, the image width will be
     * calculated according to the original aspect ratio.
     */
    public ?int $maxWidth = null;

    /**
     * @var int|null the maximum height for transformable image uploads, if both this and `maxWidth` are empty, the
     * image will be saved without applying transformations. If only `maxWidth` is set, the image height will be
     * calculated according to the original aspect ratio.
     */
    public ?int $maxHeight = null;

    /**
     * @var array containing image options which can be applied to the upload.
     * @see Transformation::$imageOptions
     */
    public array $imageOptions = [];

    /**
     * @var int|string x-position for cropped image, leave empty to crop from the center
     */
    public int|string $x = 0;

    /**
     * @var int|string y-position for cropped image, leave empty to crop from the center
     */
    public int|string $y = 0;

    /**
     * @var int|string rotating angle
     */
    public int|string $angle = 0;

    /**
     * @var bool|null whether uploads should be automatically rotated based on their EXIF data, if empty
     *     {@see Module::$autorotateImages} will be used.
     */
    public ?bool $autorotateImages = null;

    /**
     * @var array|null containing the allowed file extensions, if empty {@see Module::$allowedExtensions} will be used
     */
    public ?array $allowedExtensions = null;

    /**
     * @var bool|null whether a mime type should be used to check the extension, if null
     * {@see Module::$checkExtensionByMimeType} will be used
     */
    public ?bool $checkExtensionByMimeType = null;

    private ?int $relatedModelCount = null;

    #[Override]
    public function init(): void
    {
        $this->autorotateImages ??= static::getModule()->autorotateImages;
        $this->allowedExtensions ??= static::getModule()->allowedExtensions;
        $this->checkExtensionByMimeType ??= static::getModule()->checkExtensionByMimeType;

        parent::init();
    }

    #[Override]
    public function behaviors(): array
    {
        return [
            ...parent::behaviors(),
            'DateTimeBehavior' => DateTimeBehavior::class,
            'RedirectBehavior' => RedirectBehavior::class,
            'TrailBehavior' => TrailBehavior::class,
        ];
    }

    #[Override]
    public function rules(): array
    {
        return [
            ...parent::rules(),
            [
                ['upload'],
                'file',
                'extensions' => $this->allowedExtensions,
                'checkExtensionByMimeType' => $this->checkExtensionByMimeType,
            ],
            [
                ['folder_id', 'name', 'basename', 'extension'],
                'required',
            ],
            [
                ['status'],
                DynamicRangeValidator::class,
                'skipOnEmpty' => false,
            ],
            [
                ['folder_id', 'width', 'height', 'x', 'y', 'angle'],
                'number',
                'integerOnly' => true,
            ],
            [
                ['folder_id'],
                RelationValidator::class,
                'required' => true,
            ],
            [
                ['name', 'basename'],
                'string',
                'max' => static::BASENAME_MAX_LENGTH,
            ],
            [
                ['basename'],
                $this->validateFilename(...),
            ],
            [
                ['width'],
                $this->validateWidth(...),
            ],
            [
                ['height'],
                $this->validateHeight(...),
            ],
            [
                ['angle'],
                $this->validateAngle(...),
            ],
            [
                $this->getI18nAttributesNames(['alt_text']),
                'string',
                'max' => 250,
            ],
        ];
    }

    /**
     * Makes sure the filename does not overwrite an existing file or contains a transformation path.
     */
    public function validateFilename(): void
    {
        if (!$this->folder) {
            $this->addInvalidAttributeError('folder_id');
            return;
        }

        if ($this->isAttributeChanged('basename') || $this->isAttributeChanged('folder_id')) {
            $module = static::getModule();
            $basename = $this->basename;
            $i = 1;

            while ($this->filenameIsTaken()) {
                // Try to append a counter to generate a unique filename, throw error if `overwriteFiles` is
                // disabled, or there were many unsuccessful tries.
                if (!$module->overwriteFiles && $i < 100) {
                    $this->basename = preg_replace('/_\d+$/', '', $basename) . '_' . $i++;
                } else {
                    $this->addError('basename', Lang::t('media', 'FILE_FILE_NAME_ALREADY', [
                        'name' => $this->getFilename(),
                    ]));

                    break;
                }
            }

            $offset = strpos($this->basename, '/');
            $folder = $offset ? substr($this->basename, 0, $offset) : $this->basename;

            if ($folder && in_array(strtolower($folder), array_map(strtolower(...), array_keys($module->transformations)), true)) {
                $this->addInvalidAttributeError('basename');
            }
        }
    }

    /**
     * Determines if the current `basename` is taken. This can be either checked via the filesystem for regular files or
     * via the database for transformable images. These are not allowed to have the same name because they would create
     * the same filenames when transformed to another format such as WEBP.
     */
    protected function filenameIsTaken(): bool
    {
        if (!$this->isTransformableImage()) {
            return is_file($this->getFilePath());
        }

        return static::find()
            ->where([
                'folder_id' => $this->folder_id,
                'basename' => $this->basename,
                'extension' => static::getModule()->transformableImageExtensions,
            ])
            ->andFilterWhere(['!=', 'id', $this->id])
            ->exists();
    }

    public function validateWidth(): void
    {
        $this->validateDimensions('width', 'x');
    }

    public function validateHeight(): void
    {
        $this->validateDimensions('height', 'y');
    }

    public function validateAngle(): void
    {
        $this->angle = (int)$this->angle;

        if (abs($this->angle) > 359) {
            $this->addInvalidAttributeError('angle');
        }
    }

    protected function validateDimensions(string $sizeAttribute, string $positionAttribute): void
    {
        if (!$this->upload && $this->isTransformableImage()) {
            $this->{$positionAttribute} = max($this->{$positionAttribute} ?: 0, 0);

            if ($this->getAttribute($sizeAttribute) + $this->{$positionAttribute} > $this->getOldAttribute($sizeAttribute)) {
                $this->addInvalidAttributeError($sizeAttribute);
            }
        }

        if ($this->{$sizeAttribute} > 65535) {
            $this->addInvalidAttributeError($sizeAttribute);
        }
    }

    #[Override]
    public function beforeValidate(): bool
    {
        $this->status ??= static::STATUS_DEFAULT;

        if (!$this->folder_id) {
            $this->populateFolderRelation($this->getDefaultFolder());
        }

        if ($this->upload) {
            if ($this->upload->error === UPLOAD_ERR_NO_FILE) {
                $this->addInvalidAttributeError('upload');
            }

            if (!$this->upload->getHasError()) {
                if (!$this->name) {
                    $this->name = $this->humanizeFilename($this->upload->name);
                }

                $this->extension = $this->upload->getExtension();
                $this->size = $this->upload->size;

                $maxFilesPerFolder = static::getModule()->maxFilesPerFolder;

                $folder = $maxFilesPerFolder
                    ? ceil((($this->folder->file_count ?? 0) + 1) / $maxFilesPerFolder) . DIRECTORY_SEPARATOR
                    : '';

                $filename = static::getModule()->keepFilename
                    ? $this->upload->getBaseName()
                    : Yii::$app->getSecurity()->generateRandomString(8);

                $this->basename = $folder . basename((string)$filename, ".$this->extension");

                if ($size = Image::getImageSize($this->upload->tempName, $this->extension)) {
                    $this->width = $size[0] ?? null;
                    $this->height = $size[1] ?? null;
                }
            }
        }

        // Make sure cropping will not set width and height to zero.
        foreach (['width', 'height'] as $attribute) {
            if (!$this->getAttribute($attribute)) {
                $this->setAttribute($attribute, $this->getOldAttribute($attribute));
            }
        }

        // Sanitize basename
        if ($this->basename) {
            $this->basename = preg_replace('#\s+#', '_', $this->basename);
            $this->basename = trim((string)preg_replace('#/{2,}#', '/', trim((string)$this->basename, '/')));
            $this->basename = preg_replace('#[^_a-zA-Z0-9/\-@]+#', '', $this->basename);
        }

        return parent::beforeValidate();
    }

    #[Override]
    public function afterValidate(): void
    {
        if ($this->hasErrors()) {
            $this->deleteTemporaryUpload();

            // Make sure a valid folder is set if validation fails, otherwise file paths would break on view.
            if ($this->hasErrors('folder_id')) {
                $folder = $this->getDefaultFolder();
                $this->populateFolderRelation($folder);
            }
        }

        parent::afterValidate();
    }

    #[Override]
    public function beforeSave($insert): bool
    {
        $this->attachBehaviors([
            'BlameableBehavior' => BlameableBehavior::class,
            'TimestampBehavior' => TimestampBehavior::class,
        ]);

        if ($this->upload) {
            // Mark basename in case of same filename in upload
            $this->markAttributeDirty('basename');
        }

        if (
            !$insert
            && $this->isTransformableImage()
            && $this->hasChangedDimensions()
            && !$this->isAttributeChanged('basename')
        ) {
            // Makes sure filename is changed on image resize or rotation to bust cache.
            $this->basename = preg_replace('/@\d+(x\d+)?$/', '', $this->basename)
                . ($this->angle ? "@$this->angle" : "@{$this->width}x$this->height");
        }

        return parent::beforeSave($insert);
    }

    #[Override]
    public function afterSave($insert, $changedAttributes): void
    {
        // Prevents timeouts on file manipulations and writes to remote disks.
        @ini_set('memory_limit', '-1');
        set_time_limit(0);

        $folder = !empty($changedAttributes['folder_id'])
            ? Folder::findOne($changedAttributes['folder_id'])
            : $this->folder;

        $basename = array_key_exists('basename', $changedAttributes)
            ? $changedAttributes['basename']
            : $this->basename;

        $extension = array_key_exists('extension', $changedAttributes)
            ? $changedAttributes['extension']
            : $this->extension;

        $prevFilepath = $folder->getUploadPath() . $basename . '.' . $extension;
        $filepath = $this->getFilePath();

        if ($this->upload) {
            if (!$insert) {
                $this->deleteTransformations($folder, $basename);
                FileHelper::unlink($prevFilepath);
            }

            $this->saveUploadedFile();
        } elseif ($filepath !== $prevFilepath) {
            if (array_key_exists('folder_id', $changedAttributes) && $folder instanceof Folder) {
                $folder->recalculateFileCount()->update();
            }

            FileHelper::createDirectory(dirname($filepath));
            FileHelper::rename($prevFilepath, $filepath);

            $this->deleteTransformations($folder, $basename);
        }

        if ($this->isTransformableImage()) {
            $prevAttributes = $this->attributes;

            if (!$this->upload) {
                if (array_key_exists('width', $changedAttributes) || array_key_exists('height', $changedAttributes)) {
                    $this->cropImage();
                }

                if ($this->angle) {
                    $this->rotateImage();
                }
            }

            if (($this->maxWidth !== null && $this->maxWidth < $this->width) || ($this->maxHeight !== null && $this->maxHeight < $this->height)) {
                $this->resizeImage();
            }

            // Check if image attributes were changed in `afterSave` and mark them for `TrailBehavior`
            foreach (['width', 'height', 'size'] as $attribute) {
                if ($prevAttributes[$attribute] !== $this->{$attribute}) {
                    $changedAttributes[$attribute] ??= $prevAttributes[$attribute];
                }
            }
        }

        if (array_key_exists('folder_id', $changedAttributes)) {
            $this->folder->recalculateFileCount()->update();
        }

        static::getModule()->invalidatePageCache();

        // Clear upload to prevent the file from being re-uploaded on subsequent calls to this class.
        $this->upload = null;

        parent::afterSave($insert, $changedAttributes);
    }

    #[Override]
    public function beforeDelete(): bool
    {
        if (parent::beforeDelete()) {
            if ($this->folder) {
                $this->deleteTransformations();
            }

            return true;
        }

        return false;
    }

    #[Override]
    public function afterDelete(): void
    {
        if ($this->folder) {
            FileHelper::unlink($this->getFilePath());
            $this->folder->recalculateFileCount()->update();
        }

        static::getModule()->invalidatePageCache();

        parent::afterDelete();
    }

    public function copy(string $url): bool
    {
        $this->upload = new StreamUploadedFile([
            'allowedExtensions' => $this->allowedExtensions,
            'url' => $url,
        ]);

        return !$this->upload->getHasError();
    }

    public function deleteTransformations(?Folder $folder = null, ?string $basename = null): void
    {
        if ($this->transformation_count) {
            $folder ??= $this->folder;

            if (!$basename) {
                $basename = $this->basename;
            }

            foreach ($this->transformations as $transformation) {
                $filename = $folder->getUploadPath() . $transformation->name . DIRECTORY_SEPARATOR . $basename . '.' . $transformation->extension;
                FileHelper::unlink($filename);
            }

            if (!$this->isDeleted()) {
                // Transformation records only need to be deleted if this was an update request.
                Transformation::deleteAll(['file_id' => $this->id]);
                $this->updateAttributes(['transformation_count' => 0]);
            }
        }
    }

    public function deleteTemporaryUpload(): void
    {
        if ($this->upload?->tempName) {
            FileHelper::unlink($this->upload->tempName);
        }
    }

    protected function saveUploadedFile(): void
    {
        FileHelper::createDirectory(dirname($this->getFilePath()));
        $this->upload->saveAs($this->getFilePath());

        if ($this->isTransformableImage() && ($this->autorotateImages || $this->imageOptions)) {
            $image = Image::getImage($this->getFilePath());

            if ((new Autorotate())->getTransformations($image)) {
                $image = Image::autorotate($this->getFilePath());
                $this->updateImageInternal($image);
            }
        }
    }

    protected function resizeImage(): void
    {
        $image = Image::resize($this->getFilePath(), $this->maxWidth, $this->maxHeight);
        $this->updateImageInternal($image);
    }

    protected function cropImage(): void
    {
        $image = Image::crop($this->getFilePath(), $this->width, $this->height, [$this->x, $this->y]);
        $this->updateImageInternal($image);
    }

    protected function rotateImage(): void
    {
        $image = Image::rotate($this->getFilePath(), $this->angle);
        $this->updateImageInternal($image);
    }

    protected function updateImageInternal(ImageInterface $image): void
    {
        $filepath = $this->getFilePath();
        Image::saveImage($image, $filepath, $this->imageOptions);

        $size = Image::getImageSize($filepath);
        clearstatcache(true, $filepath);

        $this->updateAttributes([
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
            'size' => filesize($filepath),
        ]);

        $this->deleteTransformations();
    }

    /**
     * @return ActiveQuery<Folder>
     */
    public function getFolder(): ActiveQuery
    {
        return $this->hasOne(Folder::class, ['id' => 'folder_id']);
    }

    /**
     * @return ActiveQuery<Transformation>
     */
    public function getTransformations(): ActiveQuery
    {
        return $this->hasMany(Transformation::class, ['file_id' => 'id'])
            ->inverseOf('file');
    }

    public function populateFolderRelation(?Folder $folder): void
    {
        $this->populateRelation('folder', $folder);
        $this->folder_id = $folder?->id;
    }

    #[Override]
    public static function find(): FileQuery
    {
        return Yii::createObject(FileQuery::class, [static::class]);
    }

    public function humanizeFilename(string $filename): string
    {
        return StringHelper::humanizeFilename($filename);
    }

    public function recalculateTransformationCount(): static
    {
        $this->transformation_count = $this->getTransformations()->count();
        return $this;
    }

    /**
     * @return list<class-string<FileRelationInterface>>
     */
    public function getActiveRelatedModels(): array
    {
        $this->relatedModelCount = 0;
        $relations = [];

        foreach (static::getModule()->fileRelations as $relation) {
            foreach ($relation::instance()->getFileCountAttributeNames() as $attribute) {
                if ($fileCount = $this->getAttribute($attribute)) {
                    $this->relatedModelCount += $fileCount;
                    $relations[] = $relation;
                }
            }
        }

        return array_unique($relations);
    }

    public function getFileCountAttributeNames(): array
    {
        $attributeNames = [];

        foreach (static::getModule()->fileRelations as $relation) {
            foreach ($relation::instance()->getFileCountAttributeNames() as $attributeName) {
                $attributeNames[] = $attributeName;
            }
        }

        return array_unique($attributeNames);
    }

    public function getRelatedModelCount(): int
    {
        if ($this->relatedModelCount === null) {
            $this->getActiveRelatedModels();
        }

        return $this->relatedModelCount;
    }

    protected function getDefaultFolder(): Folder
    {
        return FolderCollection::getDefault();
    }

    public function getDimensions(): string
    {
        return $this->hasDimensions() ? ($this->width . ' x ' . $this->height) : '';
    }

    public function getFilename(): string
    {
        return $this->basename . '.' . $this->extension;
    }

    public function getFilePath(): string
    {
        return $this->folder->getUploadPath() . $this->getFilename();
    }

    public function getSrcset(array|string|null $transformations = null, string|null $extension = null): array
    {
        $transformations = is_string($transformations) ? [$transformations] : $transformations;
        $srcset = [];

        if ($transformations && $this->isTransformableImage()) {
            foreach ($transformations as $name) {
                if ($url = $this->getTransformationUrl($name, $extension)) {
                    $option = $this->getTransformationOptions($name);
                    $width = $option['width'] ?? (isset($option['height']) ? floor($option['height'] / $this->height * $this->width) : $this->width);
                    $srcset[$width] = $url;
                }
            }
        }

        return $srcset;
    }

    public function getTransformationNames(): array
    {
        return $this->isTransformableImage()
            ? array_filter(array_keys(static::getModule()->transformations), $this->isValidTransformation(...))
            : [];
    }

    public function getTransformationOptions(string $name): array
    {
        $options = static::getModule()->transformations[$name] ?? null;

        if (!$options) {
            throw new InvalidConfigException("Transformation '$name' does not exist.");
        }

        return $options;
    }

    public function getTransformationUrl(string $name, ?string $extension = null): ?string
    {
        if ($this->isValidTransformation($name)) {
            $folder = FolderCollection::getAll()[$this->folder_id] ?? $this->folder;
            return $folder->getUploadUrl() . $name . '/' . $this->basename . '.' . ($extension ?: $this->extension);
        }

        return null;
    }

    public function getAdminRoute(): array
    {
        return $this->id ? ['/admin/media/file/update', 'id' => $this->id] : ['/admin/media/file/index'];
    }

    public function getUrl(): string
    {
        $folder = FolderCollection::getAll()[$this->folder_id] ?? $this->folder;
        return $folder->getUploadUrl() . $this->getFilename();
    }

    /**
     * @noinspection PhpUnused
     */
    public function getUrlWithVersion(): string
    {
        return $this->getUrl() . '?v=' . ($this->updated_at?->getTimestamp() ?? '');
    }

    public function getTrailAttributes(): array
    {
        return array_diff($this->attributes(), [
            ...$this->getFileCountAttributeNames(),
            'transformation_count',
            'updated_by_user_id',
            'updated_at',
            'created_at',
        ]);
    }

    public function getTrailModelName(): string
    {
        if ($this->id) {
            return $this->name ?: Lang::t('skeleton', 'COMMON_MODEL_ID', [
                'model' => $this->getTrailModelType(),
                'id' => $this->id,
            ]);
        }

        return $this->getTrailModelType();
    }

    public function getTrailModelType(): string
    {
        return Lang::t('media', 'COMMON_FILE');
    }

    public function getTrailModelAdminRoute(): array
    {
        return $this->getAdminRoute();
    }

    public function hasPreview(): bool
    {
        return in_array($this->extension, ['avif', 'bmp', 'gif', 'jpg', 'jpeg', 'png', 'svg', 'webp'], true);
    }

    public function hasDimensions(): bool
    {
        return $this->width && $this->height;
    }

    public function hasChangedDimensions(): bool
    {
        return $this->isAttributeChanged('width') || $this->isAttributeChanged('height') || $this->angle;
    }

    /**
     * @noinspection PhpUnused
     */
    public function isVideo(): bool
    {
        return in_array($this->extension, ['mp4', 'ogg', 'webm'], true);
    }

    public function isTransformableImage(): bool
    {
        return in_array($this->extension, static::getModule()->transformableImageExtensions, true)
            && $this->hasDimensions();
    }

    public function isValidTransformation(string $name): bool
    {
        if ($this->isTransformableImage()) {
            if ($transformation = $this->getTransformationOptions($name)) {
                if ($transformation['scaleUp'] ?? false) {
                    return true;
                }

                $keepAspectRatio = !empty($transformation['keepAspectRatio']) && !empty($transformation['width']) && !empty($transformation['height']);
                $isWidthValid = empty($transformation['width']) || $transformation['width'] <= $this->width;
                $isHeightValid = empty($transformation['height']) || $transformation['height'] <= $this->height;

                return $keepAspectRatio ? ($isWidthValid || $isHeightValid) : ($isWidthValid && $isHeightValid);
            }
        }

        return false;
    }

    #[Override]
    public function attributeLabels(): array
    {
        return [
            ...parent::attributeLabels(),
            'folder_id' => Lang::t('media', 'FILE_FOLDER_ID_LABEL'),
            'basename' => Lang::t('media', 'FILE_BASENAME_LABEL'),
            'extension' => Lang::t('media', 'FILE_EXTENSION_LABEL'),
            'transformation_count' => Lang::t('media', 'FILE_TRANSFORMATION_COUNT_LABEL'),
            'dimensions' => Lang::t('media', 'FILE_DIMENSIONS_LABEL'),
            'width' => Lang::t('media', 'FILE_WIDTH_LABEL'),
            'height' => Lang::t('media', 'FILE_HEIGHT_LABEL'),
            'x' => Lang::t('media', 'FILE_X_LABEL'),
            'y' => Lang::t('media', 'FILE_Y_LABEL'),
            'size' => Lang::t('media', 'FILE_SIZE_LABEL'),
            'alt_text' => Lang::t('media', 'FILE_ALT_TEXT_LABEL'),
            'angle' => Lang::t('media', 'FILE_ANGLE_LABEL'),
        ];
    }

    #[Override]
    public function formName(): string
    {
        return 'File';
    }

    #[Override]
    public static function tableName(): string
    {
        return static::getModule()->getTableName('file');
    }
}
