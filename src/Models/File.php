<?php

declare(strict_types=1);

namespace Hirtz\Media\Models;

use Hirtz\Media\Helpers\ImageSize;
use Hirtz\Media\Images\SvgSanitizer;
use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\CustomAttributes\FileCustomAttribute;
use Hirtz\Media\Models\Queries\AssetQuery;
use Hirtz\Media\Models\Queries\FileQuery;
use Hirtz\Media\Module;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Skeleton\Behaviors\BlameableBehavior;
use Hirtz\Skeleton\Behaviors\RedirectBehavior;
use Hirtz\Skeleton\Behaviors\SearchBehavior;
use Hirtz\Skeleton\Behaviors\TimestampBehavior;
use Hirtz\Skeleton\Behaviors\TrailBehavior;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Helpers\StringHelper;
use Hirtz\Skeleton\I18n\Message;
use Hirtz\Skeleton\Models\Breadcrumb;
use Hirtz\Skeleton\Models\CustomAttributes\CustomAttribute;
use Hirtz\Skeleton\Models\CustomAttributes\NumberCustomAttribute;
use Hirtz\Skeleton\Models\Interfaces\CustomAttributeInterface;
use Hirtz\Skeleton\Models\Interfaces\DraftStatusAttributeInterface;
use Hirtz\Skeleton\Models\Interfaces\SearchableInterface;
use Hirtz\Skeleton\Models\Interfaces\TrailModelInterface;
use Hirtz\Skeleton\Models\Interfaces\TranslationInterface;
use Hirtz\Skeleton\Models\Trail;
use Hirtz\Skeleton\Models\Traits\AdminModelTrait;
use Hirtz\Skeleton\Models\Traits\CustomAttributesTrait;
use Hirtz\Skeleton\Models\Traits\DraftStatusAttributeTrait;
use Hirtz\Skeleton\Models\Traits\I18nAttributesTrait;
use Hirtz\Skeleton\Models\Traits\SearchableTrait;
use Hirtz\Skeleton\Models\Traits\TrailModelTrait;
use Hirtz\Skeleton\Models\Traits\TranslationTrait;
use Hirtz\Skeleton\Models\Traits\UpdatedByUserTrait;
use Hirtz\Skeleton\Upload\Upload;
use Hirtz\Skeleton\Validators\DynamicRangeValidator;
use Hirtz\Skeleton\Validators\FileValidator;
use Hirtz\Skeleton\Validators\RelationValidator;
use Hirtz\Skeleton\Web\ChunkedUploadedFile;
use Hirtz\Skeleton\Web\AbstractUploadedFile;
use Hirtz\Skeleton\Web\CopiedUploadedFile;
use yii\web\UploadedFile;
use Hirtz\Skeleton\Web\User as WebUser;
use Intervention\Image\Interfaces\ImageInterface;
use Hirtz\Skeleton\Models\Interfaces\StaleSaveInterface;
use Hirtz\Skeleton\Models\Traits\StaleSaveTrait;
use Override;
use Throwable;
use Yii;
use Hirtz\Skeleton\Db\DateTime;
use yii\db\ActiveQuery;
use yii\helpers\Inflector;

/**
 * @property int $id
 * @property int $folder_id
 * @property string $name
 * @property string $basename
 * @property string $extension
 * @property int|null $width
 * @property int|null $height
 * @property int|string $size
 * @property string|null $alt_text
 * @property int $transformation_count
 * @property int $asset_count
 * @property int $reference_count
 * @property DateTime|null $updated_at
 * @property DateTime $created_at
 *
 * @property-read Folder|null $folder {@see File::getFolder}
 * @property-read FileTransformation[] $transformations {@see File::getTransformations}
 * @property-read Asset[] $assets {@see File::getAssets}
 * @property-read FileReference[] $references {@see File::getReferences}
 */
class File extends ActiveRecord implements
    CustomAttributeInterface,
    DraftStatusAttributeInterface,
    SearchableInterface,
    StaleSaveInterface,
    TranslationInterface
{
    use AdminModelTrait;
    use CustomAttributesTrait {
        getCustomAttributes as getOwnCustomAttributes;
    }
    use I18nAttributesTrait;
    use SearchableTrait;
    use StaleSaveTrait;
    use TranslationTrait;
    use ModuleTrait;
    use DraftStatusAttributeTrait;
    use TrailModelTrait;
    use UpdatedByUserTrait;

    final public const string AUTH_FILE = 'file';

    public const int BASENAME_MAX_LENGTH = 250;

    /**
     * The {@see Transformation::getImageOptions()} key each format's `transformation_quality_*` attribute sets.
     */
    private const array TRANSFORMATION_QUALITY_OPTIONS = [
        'avif' => 'avifQuality',
        'webp' => 'webpQuality',
        'jpg' => 'jpegQuality',
        'jpeg' => 'jpegQuality',
    ];

    /**
     * @var int how often a taken basename is numbered before the upload is refused.
     */
    public const int MAX_FILENAME_COLLISIONS = 99;

    public const int HASH_DIRECTORY_LENGTH = 8;

    public const int MAX_PREVIEW_SIZE = 10_000_000;

    /**
     * @var AbstractUploadedFile|ChunkedUploadedFile|null the uploaded file instance
     */
    public AbstractUploadedFile|ChunkedUploadedFile|null $upload = null;

    /**
     * @var UploadedFile|null an image the browser drew of an upload no image library reads, stored as its admin
     * thumbnail
     */
    public ?UploadedFile $preview = null;

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
     * @var array<string, mixed> image options which can be applied to the upload.
     * @see Transformation::getImageOptions()
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
     * @var list<string>|null the allowed file extensions, if empty {@see Module::$allowedExtensions} will be used
     */
    public ?array $allowedExtensions = null;

    /**
     * @var bool|null whether a mime type should be used to check the extension, if null
     * {@see Module::$checkExtensionByMimeType} will be used
     */
    public ?bool $checkExtensionByMimeType = null;

    #[Override]
    public function init(): void
    {
        $this->autorotateImages ??= static::getModule()->autorotateImages;
        $this->allowedExtensions ??= array_values(static::getModule()->allowedExtensions);
        $this->checkExtensionByMimeType ??= static::getModule()->checkExtensionByMimeType;

        parent::init();
    }

    #[Override]
    public function behaviors(): array
    {
        return [
            ...parent::behaviors(),
            'RedirectBehavior' => RedirectBehavior::class,
            'SearchBehavior' => [
                'class' => SearchBehavior::class,
                'attributes' => [...SearchBehavior::STATE_ATTRIBUTES, 'basename', 'extension'],
            ],
            'TrailBehavior' => TrailBehavior::class,
        ];
    }

    #[Override]
    public function rules(): array
    {
        return [
            ...parent::rules(),
            ...$this->getStaleSaveRules(),
            [
                ['upload'],
                FileValidator::class,
                'extensions' => $this->allowedExtensions,
                'maxSize' => Upload::getComponent()->maxSize,
                'checkExtensionByMimeType' => $this->checkExtensionByMimeType,
            ],
            // The extension is the upload's, never user input: renamed, it would rename the file on disk.
            [
                ['folder_id', 'name', 'basename', '!extension'],
                'required',
            ],
            [
                ['!extension'],
                'match',
                'pattern' => '/^[a-z0-9]+$/i',
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

        // A replacement is named like a new file, so it never takes over the URL the old one is cached under.
        if (
            $this->isAttributeChanged('basename')
            || $this->isAttributeChanged('folder_id')
            || $this->isAttributeChanged('extension')
            || ($this->upload && !$this->getIsNewRecord())
        ) {
            $module = static::getModule();

            if (!$module->overwriteFiles) {
                $this->resolveFilenameCollision();
            } elseif ($this->recordIsTaken()) {
                $this->addFilenameTakenError();
            }

            $offset = strpos($this->basename, '/');
            $folder = $offset ? substr($this->basename, 0, $offset) : $this->basename;

            if ($folder && in_array(strtolower($folder), array_map(strtolower(...), $module->getTransformationNames()), true)) {
                $this->addInvalidAttributeError('basename');
            }
        }
    }

    /**
     * Numbers the basename until it is free, or reports it. With {@see Module::$overwriteFiles} on, a file on disk is
     * overwritten instead, but never another record's: that one is still reported.
     */
    protected function resolveFilenameCollision(): void
    {
        $basename = (string)$this->basename;
        $number = 0;

        while ($this->filenameIsTaken()) {
            if (++$number > static::MAX_FILENAME_COLLISIONS) {
                $this->addFilenameTakenError();
                return;
            }

            $this->basename = static::getNumberedBasename($basename, $number);
        }
    }

    /**
     * The name a file gets when the one it asked for is taken. The name is kept whole: stripping a trailing
     * `_<number>` off it first, as this used to, made `report_2023` into `report_1` and lost the year. The counter
     * is what has to fit {@see static::BASENAME_MAX_LENGTH}, so the name gives way to it rather than the other way
     * round — the `string` rule has already run by the time a collision is resolved.
     */
    public static function getNumberedBasename(string $basename, int $number): string
    {
        $suffix = "_$number";
        return substr($basename, 0, static::BASENAME_MAX_LENGTH - strlen($suffix)) . $suffix;
    }

    /**
     * Two transformable images cannot share a basename either, whatever their own extensions: both would be
     * converted to the same WEBP or AVIF filename. So both sides are asked — the file system for what is on disk,
     * including a file no record knows about, and the database for a record whose file is not written yet.
     */
    protected function filenameIsTaken(): bool
    {
        $extensions = $this->isTransformableImage()
            ? static::getModule()->transformableImageExtensions
            : [$this->extension];

        $basename = $this->folder->getUploadPath() . $this->basename;

        return ($this->writesFile() && FileHelper::isFilenameTaken($basename, $extensions))
            || $this->recordIsTaken();
    }

    protected function recordIsTaken(): bool
    {
        $extensions = $this->isTransformableImage()
            ? static::getModule()->transformableImageExtensions
            : [$this->extension];

        return static::find()
            ->where([
                'folder_id' => $this->folder_id,
                'basename' => $this->basename,
                'extension' => $extensions,
            ])
            ->andFilterWhere(['!=', 'id', $this->id])
            ->exists();
    }

    private function addFilenameTakenError(): void
    {
        $this->addError('basename', Yii::t('media', 'FILE_FILE_NAME_ALREADY', [
            'name' => $this->getFilename(),
        ]));
    }

    /**
     * Whether this save puts a file into the path — the upload, or the rename {@see static::afterSave()} runs for a
     * changed basename or folder. An insert without an upload adopts a file that is already there, so for that one
     * case the file system would only ever find the record's own.
     */
    protected function writesFile(): bool
    {
        return $this->upload || !$this->getIsNewRecord();
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

    /**
     * Without an upload the dimensions are a crop of what is already stored — which only exists once the record
     * does, so a new one has nothing to be measured against.
     */
    protected function validateDimensions(string $sizeAttribute, string $positionAttribute): void
    {
        if (!$this->upload && !$this->getIsNewRecord() && $this->isTransformableImage()) {
            $this->{$positionAttribute} = max($this->{$positionAttribute} ?: 0, 0);

            if ($this->getAttribute($sizeAttribute) + $this->{$positionAttribute} > $this->getOldAttribute($sizeAttribute)) {
                $this->addInvalidAttributeError($sizeAttribute);
            }
        }

        if ($this->{$sizeAttribute} !== null && ($this->{$sizeAttribute} < 1 || $this->{$sizeAttribute} > 65535)) {
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

                if ($this->extension === 'svg') {
                    $this->sanitizeSvgUpload();
                }

                $this->size = $this->upload->size;

                $filename = static::getModule()->keepFilename
                    ? $this->upload->getBaseName()
                    : Yii::$app->getSecurity()->generateRandomString(8);

                $directory = static::getModule()->hashDirectories
                    ? substr((string)hash_file('xxh3', (string)$this->upload->tempName), 0, static::HASH_DIRECTORY_LENGTH) . '/'
                    : '';

                $this->basename = $directory . basename((string)$filename, ".$this->extension");

                // A replacement the size cannot be read of must not pass as the image it replaces
                [$this->width, $this->height] = ImageSize::fromFile($this->upload->tempName, $this->extension)
                    ?? [null, null];
            }
        } else {
            // Make sure cropping will not set width and height to zero.
            foreach (['width', 'height'] as $attribute) {
                if (!$this->getAttribute($attribute)) {
                    $this->setAttribute($attribute, $this->getOldAttribute($attribute));
                }
            }

            // The admin form edits the name alone: a rename keeps the directory the content was hashed into.
            $directory = static::getHashDirectoryOf((string)$this->getOldAttribute('basename'));

            if ($directory !== null && !str_contains((string)$this->basename, '/')) {
                $this->basename = "$directory/$this->basename";
            }
        }

        // Sanitize basename
        if ($this->basename) {
            $this->basename = Inflector::transliterate($this->basename);
            $this->basename = preg_replace('#\s+#', '_', $this->basename);
            $this->basename = trim((string)preg_replace('#/{2,}#', '/', trim((string)$this->basename, '/')));
            $this->basename = preg_replace('#[^_a-zA-Z0-9/\-@]+#', '', $this->basename);
        }

        $this->setCacheBusterBasename();

        return parent::beforeValidate();
    }

    /**
     * The header alone tells the dimensions, so a truncated or corrupt image passed as transformable and failed on
     * every crop and every derivative instead: it is decoded once here.
     */
    protected function validateImageDecodes(): void
    {
        $module = static::getModule();
        $module->removeResourceLimits();

        try {
            $module->getImageProcessor()->read((string)$this->upload?->tempName);
        } catch (Throwable) {
            $this->addNotAnImageError();
        }
    }

    /**
     * An SVG opened on its own runs its scripts on the site's origin, so it is stored without them.
     */
    protected function sanitizeSvgUpload(): void
    {
        $tempName = (string)$this->upload?->tempName;

        if (!Yii::$container->get(SvgSanitizer::class)->sanitizeFile($tempName)) {
            $this->addNotAnImageError();
            return;
        }

        clearstatcache(true, $tempName);
        $this->upload->size = (int)filesize($tempName);
    }

    protected function addNotAnImageError(): void
    {
        $this->addError('upload', Yii::t('yii', 'The file "{file}" is not an image.', [
            'file' => $this->upload?->name,
        ]));
    }

    #[Override]
    public function afterValidate(): void
    {
        if (!$this->hasErrors() && $this->upload && $this->isTransformableImageExtension()) {
            $this->hasDimensions() ? $this->validateImageDecodes() : $this->addNotAnImageError();
        }

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

        // Marking a changed one would drop the old value `afterSave()` deletes the previous file by
        if ($this->upload && !$this->isAttributeChanged('basename')) {
            $this->markAttributeDirty('basename');
        }

        $this->setCacheBusterBasename();

        return parent::beforeSave($insert);
    }

    /**
     * A resized or rotated image gets a new basename (`photo@200x100`, `photo@90`), as does a changed transformation
     * quality (`photo-q60`), which busts every cache holding the old one. Set before validation, so a collision or the
     * length is checked like any other name; the save repeats it for one that skips validation.
     */
    protected function setCacheBusterBasename(): void
    {
        if (
            $this->getIsNewRecord()
            || $this->upload
            || !$this->isTransformableImage()
            || $this->isAttributeChanged('basename')
        ) {
            return;
        }

        $oldQualitySuffix = $this->getTransformationQualitySuffix(true);
        $qualitySuffix = $this->getTransformationQualitySuffix(false);
        $hasChangedDimensions = $this->hasChangedDimensions();

        if ($qualitySuffix === $oldQualitySuffix && !$hasChangedDimensions) {
            return;
        }

        $basename = (string)$this->basename;

        if ($oldQualitySuffix && str_ends_with($basename, $oldQualitySuffix)) {
            $basename = substr($basename, 0, -strlen($oldQualitySuffix));
        }

        $suffix = '';

        if ($hasChangedDimensions) {
            $basename = (string)preg_replace('/@\d+(x\d+)?$/', '', $basename);
            $suffix = $this->angle ? "@$this->angle" : "@{$this->width}x$this->height";
        }

        $suffix .= $qualitySuffix;

        $this->basename = substr($basename, 0, static::BASENAME_MAX_LENGTH - strlen($suffix)) . $suffix;
    }

    /**
     * `-avif-60-webp-75`. Read before validation normalized the values: one the rules will refuse keeps the stored one.
     */
    private function getTransformationQualitySuffix(bool $old): string
    {
        $suffix = '';

        foreach ($this->getTransformationQualityAttributes() as $extension => $attribute) {
            $quality = $this->getOldAttribute($attribute);

            if (!$old) {
                $value = $this->getAttribute($attribute);
                $valid = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);

                if ($value === null || $value === '' || $valid !== false) {
                    $quality = $valid ?: null;
                }
            }

            if ($quality) {
                $suffix .= "-$extension-$quality";
            }
        }

        return $suffix;
    }

    /**
     * @param array<string, mixed> $changedAttributes
     */
    #[Override]
    public function afterSave($insert, $changedAttributes): void
    {
        // Prevents timeouts on file manipulations and writes to remote disks
        static::getModule()->removeResourceLimits();

        $folder = !empty($changedAttributes['folder_id'])
            ? Folder::findOne($changedAttributes['folder_id'])
            : $this->folder;

        $basename = $changedAttributes['basename'] ?? $this->basename;

        $extension = array_key_exists('extension', $changedAttributes)
            ? $changedAttributes['extension']
            : $this->extension;

        $prevFilepath = $folder->getUploadPath() . $basename . '.' . $extension;
        $filepath = $this->getFilePath();

        if ($this->upload) {
            if (!$insert) {
                $this->deleteTransformations($folder, $basename);

                if ($prevFilepath !== $filepath) {
                    FileHelper::unlink($prevFilepath);
                    FileHelper::removeEmptyDirectories($prevFilepath, $folder->getUploadPath());
                }
            }

            $this->saveUploadedFile();
            $this->insertPreview();
        } elseif ($filepath !== $prevFilepath) {
            FileHelper::createDirectory(dirname($filepath));
            FileHelper::rename($prevFilepath, $filepath);
            FileHelper::removeEmptyDirectories($prevFilepath, $folder->getUploadPath());

            if ($this->hasChangedImage($changedAttributes)) {
                $this->deleteTransformations($folder, $basename);
            } else {
                $this->moveTransformations($folder, $basename);
            }
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

            if ($this->shouldResizeImage()) {
                $this->resizeImage();
            }

            // Check if image attributes were changed in `afterSave` and mark them for `TrailBehavior`
            foreach (['width', 'height', 'size'] as $attribute) {
                if ($prevAttributes[$attribute] !== $this->{$attribute}) {
                    $changedAttributes[$attribute] ??= $prevAttributes[$attribute];
                }
            }
        }

        if (!$this->getIsBatch() && array_key_exists('folder_id', $changedAttributes)) {
            if (!empty($changedAttributes['folder_id']) && $folder instanceof Folder) {
                $folder->updateFileCount();
            }

            $this->folder->updateFileCount();
        }

        static::getModule()->invalidatePageCache();

        // Clear upload to prevent the file from being re-uploaded on subsequent calls to this class.
        $this->upload = null;

        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * The delete clears the file from every record using it, which has to happen with the delete or not at all.
     *
     * @return array<string, int>
     */
    #[Override]
    public function transactions(): array
    {
        return [$this->getScenario() => static::OP_DELETE];
    }

    #[Override]
    public function beforeDelete(): bool
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        $this->clearReferences();

        // Deleted through the models rather than by the cascade, so the counts and trails of both sides are written.
        $assets = $this->getAssets()->all();
        Asset::populateModelRelations($assets);

        foreach ($assets as $asset) {
            // An earlier delete can take one along (a cms hotspot's own asset); deleting it again runs its hooks.
            if (count($assets) > 1 && !Asset::find()->where(['id' => $asset->id])->exists()) {
                continue;
            }

            $asset->populateFileRelation($this);
            $asset->delete();
        }

        if ($this->folder) {
            $this->deleteTransformations();
        }

        return true;
    }

    #[Override]
    public function afterDelete(): void
    {
        if ($this->folder) {
            FileHelper::unlink($this->getFilePath());
            FileHelper::removeEmptyDirectories($this->getFilePath(), $this->folder->getUploadPath());

            if (!$this->getIsBatch() && !$this->folder->isDeleted()) {
                $this->folder->updateFileCount();
            }
        }

        if (!$this->getIsBatch()) {
            static::getModule()->invalidatePageCache();
        }

        parent::afterDelete();
    }

    /**
     * Writes the cleared values without validation or hooks, as a required value is cleared too: the record asks for
     * it on its next save. The foreign key's cascade only catches a delete that skipped the model.
     */
    protected function clearReferences(): void
    {
        $references = $this->getReferences()->all();
        FileReference::populateModelRelations($references);

        foreach ($references as $reference) {
            $reference->populateFileRelation($this);
            $model = $reference->getModel();

            if ($model instanceof CustomAttributeInterface
                && $model->clearCustomAttributeRecordId(FileCustomAttribute::class, $this->id)) {
                $model->updateDenormalizedAttributes([
                    $model->getCustomAttributesColumn() => $model->getSerializedCustomAttributesColumn(),
                ]);

                if ($model instanceof TrailModelInterface) {
                    Trail::createOrderTrail($model, Message::make('media', 'FILE_REFERENCE_CLEARED', [
                        'file' => $this->getFilename(),
                        'attributes' => implode(', ', array_keys($reference->getAttributeLabelCounts())),
                    ]));
                }
            }

            $reference->setIsBatch(true);
            $reference->delete();
        }
    }

    /**
     * @param string $path a path the application names, never one that arrived with a request — a URL is fetched
     * by {@see \Hirtz\Skeleton\Web\StreamUploadedFile} under the policy of the `upload` component.
     */
    public function copy(string $path): bool
    {
        $this->upload = new CopiedUploadedFile([
            'allowedExtensions' => $this->allowedExtensions,
            'path' => $path,
        ]);

        return !$this->upload->getHasError();
    }

    /**
     * @param array<string, mixed> $changedAttributes
     */
    protected function hasChangedImage(array $changedAttributes): bool
    {
        return array_key_exists('extension', $changedAttributes)
            || array_key_exists('width', $changedAttributes)
            || array_key_exists('height', $changedAttributes)
            || (bool)$this->angle
            || $this->hasChangedTransformationQuality()
            || $this->shouldResizeImage();
    }

    protected function shouldResizeImage(): bool
    {
        return ($this->maxWidth !== null && $this->maxWidth < $this->width)
            || ($this->maxHeight !== null && $this->maxHeight < $this->height);
    }

    /**
     * A derivative whose file is gone is deleted rather than moved: the surviving record keeps the on-demand
     * route from recreating it, which fails its own uniqueness rule, and the thumbnail 404s for good.
     */
    public function moveTransformations(Folder $folder, string $basename): void
    {
        if (!$this->transformation_count) {
            return;
        }

        foreach ($this->transformations as $transformation) {
            $prevFilepath = $folder->getUploadPath() . $transformation->name . DIRECTORY_SEPARATOR
                . $basename . '.' . $transformation->extension;

            if (!is_file($prevFilepath)) {
                $transformation->delete();
                continue;
            }

            $filepath = $this->folder->getUploadPath() . $transformation->name . DIRECTORY_SEPARATOR
                . $this->basename . '.' . $transformation->extension;

            FileHelper::createDirectory(dirname($filepath));
            FileHelper::rename($prevFilepath, $filepath);
            FileHelper::removeEmptyDirectories($prevFilepath, $folder->getUploadPath());
        }
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
                FileHelper::removeEmptyDirectories($filename, $folder->getUploadPath() . $transformation->name);
            }

            if (!$this->isDeleted()) {
                // Transformation records only need to be deleted if this was an update request.
                FileTransformation::deleteAll(['file_id' => $this->id]);
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

    protected function insertPreview(): void
    {
        if (
            !$this->preview
            || $this->preview->getHasError()
            || $this->preview->size > static::MAX_PREVIEW_SIZE
            || $this->isTransformableImage()
        ) {
            return;
        }

        $transformation = FileTransformation::create();
        $transformation->name = Transformation::NAME_ADMIN;
        $transformation->extension = static::getModule()->getPreviewExtension();
        $transformation->sourcePath = $this->preview->tempName;
        $transformation->populateFileRelation($this);

        if (!$transformation->insert()) {
            Yii::warning("The preview of file \"{$this->getFilename()}\" was not saved.");
        }

        $this->preview = null;
    }

    protected function saveUploadedFile(): void
    {
        FileHelper::createDirectory(dirname($this->getFilePath()));
        $this->upload->saveAs($this->getFilePath());

        if ($this->isTransformableImage() && ($this->autorotateImages || $this->imageOptions)) {
            $this->orientImage();
        }
    }

    /**
     * Rewrites an image stored sideways by its EXIF orientation upright, which corrects its width and height and
     * deletes its transformations. Answers whether it had to.
     */
    public function orientImage(): bool
    {
        if (!$this->isTransformableImage() || ImageSize::getOrientation($this->getFilePath()) === 1) {
            return false;
        }

        $this->updateImageInternal(static::getModule()->getImageProcessor()->read($this->getFilePath()));

        // the versioned URL and the cached pages carry the sideways bytes and dimensions until both move on
        $this->updateAttributes(['updated_at' => new DateTime()]);
        static::getModule()->invalidatePageCache();

        return true;
    }

    protected function resizeImage(): void
    {
        $image = static::getModule()->getImageProcessor()->read($this->getFilePath())
            ->scaleDown($this->maxWidth, $this->maxHeight);

        $this->updateImageInternal($image);
    }

    protected function cropImage(): void
    {
        $image = static::getModule()->getImageProcessor()->read($this->getFilePath())
            ->crop($this->width, $this->height, (int)$this->x, (int)$this->y);

        $this->updateImageInternal($image);
    }

    protected function rotateImage(): void
    {
        $image = static::getModule()->getImageProcessor()->read($this->getFilePath())
            ->rotate((int)$this->angle);

        $this->updateImageInternal($image);
    }

    protected function updateImageInternal(ImageInterface $image): void
    {
        $filepath = $this->getFilePath();
        static::getModule()->getImageProcessor()->write($image, $filepath, $this->imageOptions);

        $size = ImageSize::fromFile($filepath);
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
     * @return AssetQuery<Asset>
     */
    public function getAssets(): AssetQuery
    {
        /** @var AssetQuery<Asset> */
        return $this->hasMany(Asset::class, ['file_id' => 'id']);
    }

    /**
     * @return ActiveQuery<FileReference>
     */
    public function getReferences(): ActiveQuery
    {
        return $this->hasMany(FileReference::class, ['file_id' => 'id'])
            ->inverseOf('file');
    }

    /**
     * @return ActiveQuery<FileTransformation>
     */
    public function getTransformations(): ActiveQuery
    {
        return $this->hasMany(FileTransformation::class, ['file_id' => 'id'])
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

    public function updateTransformationCount(): int
    {
        return $this->updateUsageCount('transformation_count', (int)$this->getTransformations()->count());
    }

    public function updateAssetCount(): int
    {
        return $this->updateUsageCount('asset_count', (int)$this->getAssets()->count());
    }

    public function updateReferenceCount(): int
    {
        return $this->updateUsageCount('reference_count', (int)$this->getReferences()->sum('use_count'));
    }

    /**
     * What uses a file is no change to it, and `updated_at` versions its URL and sorts the library and the picker.
     */
    protected function updateUsageCount(string $attribute, int $count): int
    {
        return $this->getAttribute($attribute) !== $count ? $this->updateAttributes([$attribute => $count]) : 0;
    }

    protected function getDefaultFolder(): Folder
    {
        return FolderCollection::getDefault();
    }

    public function getDimensions(): string
    {
        return $this->hasDimensions() ? ($this->width . ' x ' . $this->height) : '';
    }

    public function getHashDirectory(): ?string
    {
        return static::getHashDirectoryOf((string)$this->basename);
    }

    protected static function getHashDirectoryOf(string $basename): ?string
    {
        $offset = strpos($basename, '/');
        $directory = $offset === static::HASH_DIRECTORY_LENGTH ? substr($basename, 0, $offset) : null;

        return $directory !== null && ctype_xdigit($directory) ? $directory : null;
    }

    public function getPreviewUrl(): ?string
    {
        if (!$this->transformation_count || $this->isTransformableImage()) {
            return null;
        }

        $folder = FolderCollection::getAll()[$this->folder_id] ?? $this->folder;
        $extension = static::getModule()->getPreviewExtension();

        return $folder->getUploadUrl() . Transformation::NAME_ADMIN . '/' . $this->basename . '.' . $extension;
    }

    public function getFilename(): string
    {
        return $this->basename . '.' . $this->extension;
    }

    public function getDisplayFilename(): string
    {
        $directory = $this->getHashDirectory();
        $filename = $this->getFilename();

        return $directory === null ? $filename : substr($filename, strlen($directory) + 1);
    }

    public function getFilePath(): string
    {
        return $this->folder->getUploadPath() . $this->getFilename();
    }

    /**
     * @param list<string>|string|null $transformations
     * @return array<int, string>
     */
    public function getSrcset(array|string|null $transformations = null, string|null $extension = null): array
    {
        $transformations = is_string($transformations) ? [$transformations] : $transformations;
        $srcset = [];

        if ($transformations && $this->isTransformableImage()) {
            foreach ($transformations as $name) {
                $transformation = static::getModule()->getTransformation($name);

                if ($transformation?->isApplicableTo($this) && ($url = $this->getTransformationUrl($name, $extension))) {
                    $srcset[$transformation->getWidthFor($this)] = $url;
                }
            }
        }

        return $srcset;
    }

    /**
     * @return list<string>
     */
    public function getTransformationNames(): array
    {
        return $this->isTransformableImage()
            ? array_values(array_filter(static::getModule()->getTransformationNames(), $this->isValidTransformation(...)))
            : [];
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

    public function getAdminIndexBreadcrumb(): Breadcrumb
    {
        return new Breadcrumb(Yii::t('media', 'COMMON_FILES'), [
            '/admin/media/file/index',
            'folder' => $this->folder_id,
        ]);
    }

    public function getPermissionName(): string
    {
        return self::AUTH_FILE;
    }

    /**
     * `filename` is {@see static::getFilename()}, so a search for `photo.jpg` finds the file its `basename`
     * alone never did.
     */
    public function getSearchAttributes(): array
    {
        return ['name', 'filename', 'alt_text'];
    }

    public function getSearchWeight(): float
    {
        return 0.5;
    }

    protected function isSearchResultVisible(): bool
    {
        return WebUser::current()?->can(static::AUTH_FILE) ?? false;
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

    /**
     * @return list<string>
     */
    public function getTrailAttributes(): array
    {
        return array_values(array_diff($this->attributes(), [
            $this->getCustomAttributesColumn(),
            'asset_count',
            'reference_count',
            'transformation_count',
            'updated_by_user_id',
            'updated_at',
            'created_at',
        ]));
    }

    public function getAdminType(): string
    {
        return Yii::t('media', 'COMMON_FILE');
    }

    protected function getDefaultAdminIcon(): ?string
    {
        return 'file';
    }

    public function hasPreview(): bool
    {
        return in_array($this->extension, ['avif', 'bmp', 'gif', 'jpg', 'jpeg', 'png', 'svg', 'webp'], true);
    }

    public function hasDimensions(): bool
    {
        return $this->width && $this->height;
    }

    public function hasChangedTransformationQuality(): bool
    {
        foreach ($this->getTransformationQualityAttributes() as $attribute) {
            if ($this->isAttributeChanged($attribute)) {
                return true;
            }
        }

        return false;
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
        return $this->isTransformableImageExtension() && $this->hasDimensions();
    }

    public function isTransformableImageExtension(): bool
    {
        return in_array($this->extension, static::getModule()->transformableImageExtensions, true);
    }

    /**
     * @return list<CustomAttribute>
     */
    #[Override]
    public function getCustomAttributes(): array
    {
        return [
            ...$this->getDefaultCustomAttributes(),
            ...$this->getOwnCustomAttributes(),
        ];
    }

    /**
     * @return list<CustomAttribute>
     */
    protected function getDefaultCustomAttributes(): array
    {
        $module = static::getModule();
        $visible = $module->getTransformationQualityExtensions();
        $attributes = [];

        foreach ($this->getTransformationQualityAttributes() as $extension => $attribute) {
            $option = self::TRANSFORMATION_QUALITY_OPTIONS[$extension];

            $attributes[] = NumberCustomAttribute::make($attribute)
                ->label(Yii::t('media', 'FILE_TRANSFORMATION_QUALITY_LABEL', [
                    'format' => strtoupper($extension),
                ]))
                ->placeholder((string)$module->$option)
                ->min(1)
                ->max(100)
                ->visible(fn (): bool => in_array($extension, $visible, true) && $this->isTransformableImage());
        }

        foreach ($module->fileCustomAttributes as $callback) {
            array_push($attributes, ...$callback($this));
        }

        return $attributes;
    }

    /**
     * Declared for every configured format, not only the visible ones, so a value outlives a server that cannot
     * encode its format and never applies to another.
     *
     * @return array<string, string> attribute names by extension
     */
    public function getTransformationQualityAttributes(): array
    {
        $attributes = [];

        foreach (static::getModule()->transformationExtensions as $extension) {
            if (isset(self::TRANSFORMATION_QUALITY_OPTIONS[$extension])) {
                $attributes[$extension] = "transformation_quality_$extension";
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTransformationImageOptions(Transformation $transformation, string $extension): array
    {
        $options = $transformation->getImageOptions();
        $attribute = $this->getTransformationQualityAttributes()[$extension] ?? null;

        if ($attribute && $quality = $this->getAttribute($attribute)) {
            $options[self::TRANSFORMATION_QUALITY_OPTIONS[$extension]] = $quality;
        }

        return $options;
    }

    public function isValidTransformation(string $name): bool
    {
        return static::getModule()->getTransformation($name)?->isApplicableTo($this) ?? false;
    }

    #[Override]
    public function attributeLabels(): array
    {
        return [
            ...parent::attributeLabels(),
            'folder_id' => Yii::t('media', 'FILE_FOLDER_ID_LABEL'),
            'basename' => Yii::t('media', 'FILE_BASENAME_LABEL'),
            'extension' => Yii::t('media', 'FILE_EXTENSION_LABEL'),
            'transformation_count' => Yii::t('media', 'FILE_TRANSFORMATION_COUNT_LABEL'),
            'reference_count' => Yii::t('media', 'FILE_REFERENCE_COUNT_LABEL'),
            'dimensions' => Yii::t('media', 'FILE_DIMENSIONS_LABEL'),
            'width' => Yii::t('media', 'FILE_WIDTH_LABEL'),
            'height' => Yii::t('media', 'FILE_HEIGHT_LABEL'),
            'x' => Yii::t('media', 'FILE_X_LABEL'),
            'y' => Yii::t('media', 'FILE_Y_LABEL'),
            'size' => Yii::t('media', 'FILE_SIZE_LABEL'),
            'alt_text' => Yii::t('media', 'FILE_ALT_TEXT_LABEL'),
            'angle' => Yii::t('media', 'FILE_ANGLE_LABEL'),
        ];
    }

    #[Override]
    public function formName(): string
    {
        return 'File';
    }

    public function getTranslationModelClass(): string
    {
        return self::class;
    }

    #[Override]
    public static function tableName(): string
    {
        return '{{%file}}';
    }
}
