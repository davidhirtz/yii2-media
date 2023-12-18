<?php

namespace davidhirtz\yii2\media\models;

use davidhirtz\yii2\datetime\DateTime;
use davidhirtz\yii2\media\models\collections\FolderCollection;
use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use davidhirtz\yii2\media\models\queries\FileQuery;
use davidhirtz\yii2\media\Module;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\behaviors\BlameableBehavior;
use davidhirtz\yii2\skeleton\behaviors\RedirectBehavior;
use davidhirtz\yii2\skeleton\behaviors\TimestampBehavior;
use davidhirtz\yii2\skeleton\behaviors\TrailBehavior;
use davidhirtz\yii2\skeleton\db\ActiveQuery;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use davidhirtz\yii2\skeleton\helpers\FileHelper;
use davidhirtz\yii2\skeleton\helpers\Image;
use davidhirtz\yii2\skeleton\helpers\StringHelper;
use davidhirtz\yii2\skeleton\models\interfaces\DraftStatusAttributeInterface;
use davidhirtz\yii2\skeleton\models\traits\DraftStatusAttributeTrait;
use davidhirtz\yii2\skeleton\models\traits\I18nAttributesTrait;
use davidhirtz\yii2\skeleton\models\traits\UpdatedByUserTrait;
use davidhirtz\yii2\skeleton\validators\DynamicRangeValidator;
use davidhirtz\yii2\skeleton\validators\RelationValidator;
use davidhirtz\yii2\skeleton\web\ChunkedUploadedFile;
use davidhirtz\yii2\skeleton\web\StreamUploadedFile;
use Imagine\Filter\Basic\Autorotate;
use Imagine\Image\ImageInterface;
use Yii;

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
 * @property DateTime $updated_at
 * @property DateTime $created_at
 *
 * @property-read Folder|null $folder {@see File::getFolder}
 * @property-read Transformation[] $transformations {@see File::getTransformations}
 */
class File extends ActiveRecord implements DraftStatusAttributeInterface
{
    use I18nAttributesTrait;
    use ModuleTrait;
    use DraftStatusAttributeTrait;
    use UpdatedByUserTrait;

    public const BASENAME_MAX_LENGTH = 250;

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

    private ?int $_assetCount = null;

    public function init(): void
    {
        $this->autorotateImages ??= static::getModule()->autorotateImages;
        $this->allowedExtensions ??= static::getModule()->allowedExtensions;
        $this->checkExtensionByMimeType ??= static::getModule()->checkExtensionByMimeType;

        parent::init();
    }

    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'RedirectBehavior' => RedirectBehavior::class,
            'TrailBehavior' => TrailBehavior::class,
        ]);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
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
        ]);
    }

    /**
     * Makes sure the filename does not overwrite an existing file or contains a transformation path.
     */
    public function validateFilename(): void
    {
        $this->status ??= static::STATUS_DEFAULT;

        if ($this->folder) {
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
                        $this->addError('basename', Yii::t('media', 'A file with the name "{name}" already exists.', [
                            'name' => $this->getFilename(),
                        ]));

                        break;
                    }
                }

                if ($folder = substr($this->basename, 0, strpos($this->basename, '/'))) {
                    if (in_array(strtolower($folder), array_map('strtolower', array_keys(static::getModule()->transformations)))) {
                        $this->addInvalidAttributeError('basename');
                    }
                }
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
        if ($this->angle && abs($this->angle) > 359) {
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

    public function beforeValidate(): bool
    {
        if (!$this->folder_id) {
            $this->populateFolderRelation($this->getDefaultFolder());
        }

        if ($this->upload) {
            if (!$this->upload->getHasError()) {
                if (!$this->name) {
                    $this->name = $this->humanizeFilename($this->upload->name);
                }

                $this->basename = !static::getModule()->keepFilename ? FileHelper::generateRandomFilename() : $this->upload->getBaseName();

                if ($maxFilesPerFolder = static::getModule()->maxFilesPerFolder) {
                    $this->basename = ceil((($this->folder->file_count ?? 0) + 1) / $maxFilesPerFolder) . DIRECTORY_SEPARATOR . $this->basename;
                }

                $this->extension = $this->upload->getExtension();
                $this->size = $this->upload->size;

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

        // Sanitize basename.
        if ($this->basename) {
            $this->basename = trim(preg_replace('#/{2,}#', '/', trim($this->basename, '/')));
            $this->basename = preg_replace('#[^_a-zA-Z0-9/\-@]+#', '', $this->basename);
        }

        return parent::beforeValidate();
    }

    /**
     * Tries to delete file on error.
     */
    public function afterValidate(): void
    {
        if ($this->hasErrors()) {
            if ($this->upload->tempName ?? false) {
                FileHelper::unlink($this->upload->tempName);
            }

            // Make sure a valid folder is set if validation fails, otherwise file paths would break on view.
            if ($this->hasErrors('folder_id')) {
                $folder = $this->getDefaultFolder();
                $this->populateFolderRelation($folder);
            }
        }

        parent::afterValidate();
    }

    public function beforeSave($insert): bool
    {
        $this->attachBehaviors([
            'BlameableBehavior' => BlameableBehavior::class,
            'TimestampBehavior' => TimestampBehavior::class,
        ]);

        if (!$insert) {
            // Makes sure filename is changed on image resize or rotation to bust cache.
            if ($this->isTransformableImage() && $this->hasChangedDimensions()) {
                if (!$this->isAttributeChanged('basename')) {
                    $this->basename = preg_replace('/@\d+(x\d+)?$/', '', $this->basename) . ($this->angle ? "@$this->angle" : "@{$this->width}x$this->height");
                }
            }
        }

        return parent::beforeSave($insert);
    }

    public function afterSave($insert, $changedAttributes): void
    {
        // Prevents timeouts on file manipulations and writes to remote disks.
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        // Get old folder and name to apply changes to the file system
        $folder = !empty($changedAttributes['folder_id']) ? Folder::findOne($changedAttributes['folder_id']) : $this->folder;
        $basename = array_key_exists('basename', $changedAttributes) ? $changedAttributes['basename'] : $this->basename;
        $extension = array_key_exists('extension', $changedAttributes) ? $changedAttributes['extension'] : $this->extension;

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

        // Run parent events then remove upload from memory. Not sure if this is necessary.
        parent::afterSave($insert, $changedAttributes);
        $this->upload = null;
    }

    /**
     * Delete assets to trigger their afterDelete clean up, related methods can check
     * for {@see File::isDeleted} to prevent unnecessary updates.
     */
    public function beforeDelete(): bool
    {
        if (parent::beforeDelete()) {
            foreach ($this->getAssetModels() as $model) {
                $assets = $model::instance()::find()
                    ->where(['file_id' => $this->id])
                    ->all();

                foreach ($assets as $asset) {
                    $asset->populateRelation('file', $this);
                    $asset->delete();
                }
            }

            if ($this->folder) {
                $this->deleteTransformations();
            }

            return true;
        }

        return false;
    }

    public function afterDelete(): void
    {
        if ($this->folder) {
            FileHelper::unlink($this->getFilePath());
            $this->folder->recalculateFileCount()->update();
        }

        static::getModule()->invalidatePageCache();

        parent::afterDelete();
    }

    public function upload(): bool
    {
        $this->upload = ChunkedUploadedFile::getInstance($this, 'upload');
        $this->autorotateImages = true;

        return $this->upload && !$this->upload->isPartial();
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
            if (!$folder) {
                $folder = $this->folder;
            }

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

    public function getFolder(): ActiveQuery
    {
        return $this->hasOne(Folder::class, ['id' => 'folder_id']);
    }

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

    public function recalculateAssetCountByAsset(AssetInterface $asset): static
    {
        $this->{$asset->getFileCountAttribute()} = $asset::instance()::find()->where(['file_id' => $this->id])->count();
        return $this;
    }

    /**
     * @return AssetInterface[]
     */
    public function getAssetModels(): array
    {
        $assets = [];
        $this->_assetCount = 0;

        foreach (static::getModule()->assets as $asset) {
            $asset = $asset::instance();

            if ($assetCount = $this->getAttribute($asset->getFileCountAttribute())) {
                $this->_assetCount += $assetCount;
                $assets[] = $asset;
            }
        }

        return $assets;
    }

    public function getAssetCount(): int
    {
        if ($this->_assetCount === null) {
            $this->getAssetModels();
        }

        return $this->_assetCount;
    }

    protected function getDefaultFolder(): Folder
    {
        return Folder::getDefault();
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

    public function getHeightPercentage(): float|bool
    {
        return $this->height && $this->width ? round($this->height / $this->width * 100, 2) : false;
    }

    public function getSrcset(array|string|null $transformations = null, string|null $extension = null): array|string
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

        return $srcset ?: $this->getUrl();
    }

    public function getTransformationNames(): array
    {
        return $this->isTransformableImage()
            ? array_filter(array_keys(static::getModule()->transformations), fn (string $name) => $this->isValidTransformation($name))
            : [];
    }

    public function getTransformationOptions(string $name, ?string $key = null): ?array
    {
        $module = static::getModule();
        return $key ? ($module->transformations[$name][$key] ?? null) : $module->transformations[$name];
    }

    public function getTransformationUrl(string $name, ?string $extension = null): ?string
    {
        if ($this->isValidTransformation($name)) {
            return $this->folder->getUploadUrl() . $name . '/' . $this->basename . '.' . ($extension ?: $this->extension);
        }

        return null;
    }

    public function getUrl(): string
    {
        $folder = FolderCollection::getAll()[$this->folder_id] ?? $this->folder;
        return $folder->getUploadUrl() . $this->getFilename();
    }

    public function getTrailAttributes(): array
    {
        $countColumns = array_map(fn ($class) => $class::instance()->getFileCountAttribute(), static::getModule()->assets);

        return array_diff($this->attributes(), $this->getI18nAttributesNames([
            ...$countColumns,
            'transformation_count',
            'updated_by_user_id',
            'updated_at',
            'created_at',
        ]));
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
        return Yii::t('media', 'File');
    }

    public function getTrailModelAdminRoute(): bool|array
    {
        return $this->id ? ['/admin/file/update', 'id' => $this->id] : false;
    }

    public function hasPreview(): bool
    {
        return in_array($this->extension, ['bmp', 'gif', 'jpg', 'jpeg', 'png', 'svg', 'webp']);
    }

    public function hasDimensions(): bool
    {
        return $this->width && $this->height;
    }

    public function hasChangedDimensions(): bool
    {
        return $this->isAttributeChanged('width') || $this->isAttributeChanged('height') || $this->angle;
    }

    public function isVideo(): bool
    {
        return in_array($this->extension, ['mp4', 'ogg', 'webm']);
    }

    public function isTransformableImage(): bool
    {
        return in_array($this->extension, static::getModule()->transformableImageExtensions) && $this->hasDimensions();
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

    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            'folder_id' => Yii::t('media', 'Folder'),
            'basename' => Yii::t('media', 'Filename'),
            'extension' => Yii::t('media', 'Extension'),
            'transformation_count' => Yii::t('media', 'Transformations'),
            'dimensions' => Yii::t('media', 'Dimensions'),
            'width' => Yii::t('media', 'Width'),
            'height' => Yii::t('media', 'Height'),
            'x' => Yii::t('media', 'Selection'),
            'y' => Yii::t('media', 'Selection'),
            'size' => Yii::t('media', 'Size'),
            'alt_text' => Yii::t('media', 'Alt text'),
            'angle' => Yii::t('media', 'Image rotation'),
        ]);
    }

    public function formName(): string
    {
        return 'File';
    }

    public static function tableName(): string
    {
        return static::getModule()->getTableName('file');
    }
}
