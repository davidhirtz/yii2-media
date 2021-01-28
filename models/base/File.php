<?php

namespace davidhirtz\yii2\media\models\base;

use davidhirtz\yii2\media\models\AssetInterface;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\models\queries\FileQuery;
use davidhirtz\yii2\datetime\DateTime;
use davidhirtz\yii2\media\models\Transformation;
use davidhirtz\yii2\media\modules\admin\widgets\forms\FileActiveForm;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\db\ActiveQuery;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use davidhirtz\yii2\skeleton\db\I18nAttributesTrait;
use davidhirtz\yii2\skeleton\db\StatusAttributeTrait;
use davidhirtz\yii2\skeleton\helpers\FileHelper;
use davidhirtz\yii2\skeleton\helpers\Image;
use davidhirtz\yii2\skeleton\models\queries\UserQuery;
use davidhirtz\yii2\skeleton\models\User;
use davidhirtz\yii2\skeleton\web\ChunkedUploadedFile;
use davidhirtz\yii2\skeleton\web\StreamUploadedFile;
use Imagine\Image\ImageInterface;
use Yii;
use yii\helpers\StringHelper;

/**
 * Class File
 * @package davidhirtz\yii2\media\models\base
 * @see \davidhirtz\yii2\media\models\File
 *
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
 * @property int $updated_by_user_id
 * @property DateTime $updated_at
 * @property DateTime $created_at
 *
 * @property Folder $folder {@link \davidhirtz\yii2\media\models\File::getFolder()}
 * @property Transformation[] $transformations {@link \davidhirtz\yii2\media\models\File::getTransformations()}
 * @property User $updated {@link \davidhirtz\yii2\media\models\File::getUpdated()}
 *
 * @method static \davidhirtz\yii2\media\models\File findOne($condition)
 */
class File extends ActiveRecord
{
    use I18nAttributesTrait;
    use ModuleTrait;
    use StatusAttributeTrait;

    /**
     * @var ChunkedUploadedFile|StreamUploadedFile
     */
    public $upload;

    /**
     * @var int the maximum width for transformable image uploads, if both this and `maxHeight` are empty the image will
     * be saved without applying transformations. If only `maxHeight` is set, the image width will be calculated according
     * to the original aspect ratio.
     */
    public $maxWidth;

    /**
     * @var int the maximum height for transformable image uploads, if both this and `maxWidth` are empty the image will
     * be saved without applying transformations. If only `maxWidth` is set, the image height will be calculated according
     * to the original aspect ratio.
     */
    public $maxHeight;

    /**
     * @var array containing additional image options which can be applied to the upload, see
     * {@link ManipulatorInterface::save()}.
     */
    public $imageOptions = [];

    /**
     * @var int x-position for cropped image, leave empty to crop from center
     */
    public $x;

    /**
     * @var int y-position for cropped image, leave empty to crop from center
     */
    public $y;

    /**
     * @var int rotating angle
     */
    public $angle;

    /**
     * @var int
     */
    private $_assetCount;

    /**
     * @var string
     */
    private $resource;

    /**
     * @inheritDoc
     */
    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'TrailBehavior' => 'davidhirtz\yii2\skeleton\behaviors\TrailBehavior',
        ]);
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [
                ['upload'],
                'file',
                'extensions' => static::getModule()->allowedExtensions,
                'checkExtensionByMimeType' => static::getModule()->checkExtensionByMimeType,
                'skipOnEmpty' => !$this->getIsNewRecord(),
            ],
            [
                ['folder_id', 'name', 'basename', 'extension'],
                'required',
            ],
            [
                ['status'],
                'davidhirtz\yii2\skeleton\validators\DynamicRangeValidator',
            ],
            [
                ['folder_id', 'width', 'height', 'x', 'y', 'angle'],
                'number',
                'integerOnly' => true,
            ],
            [
                ['folder_id'],
                'davidhirtz\yii2\skeleton\validators\RelationValidator',
                'relation' => 'folder',
                'required' => true,
            ],
            [
                ['name', 'basename'],
                'string',
                'max' => 250,
            ],
            [
                ['basename'],
                /** {@link \davidhirtz\yii2\media\models\File::validateFilename()} */
                'validateFilename',
            ],
            [
                ['width'],
                /** {@link \davidhirtz\yii2\media\models\File::validateWidth()} */
                'validateWidth',
            ],
            [
                ['height'],
                /** {@link \davidhirtz\yii2\media\models\File::validateHeight()} */
                'validateHeight',
            ],
            [
                ['angle'],
                /** {@link \davidhirtz\yii2\media\models\File::validateAngle()} */
                'validateAngle',
            ],
            [
                $this->getI18nAttributesNames(['alt_text']),
                'string',
                'max' => 250,
            ],
        ]);
    }

    /**
     * Makes sure the filename does not overwrite an existing file or contains a transformation
     * path.
     */
    public function validateFilename()
    {
        if ($this->folder) {
            if ($this->isAttributeChanged('basename')) {
                $module = static::getModule();
                $i = 1;

                while (is_file($this->getFilePath())) {
                    if (!$module->overwriteFiles) {
                        $this->basename = $this->basename . '_' . $i++ . '.' . $this->extension;
                    } else {
                        $this->addError('basename', Yii::t('media', 'A file with the name "{name}" already exists.', ['name' => $this->getFilename()]));
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
     * Validates the file image width.
     */
    public function validateWidth()
    {
        $this->validateDimensions('width', 'x');
    }

    /**
     * Validates the file image height.
     */
    public function validateHeight()
    {
        $this->validateDimensions('height', 'y');
    }

    /**
     * Validates rotation angle.
     */
    public function validateAngle()
    {
        if ($this->angle && abs($this->angle) > 359) {
            $this->addInvalidAttributeError('angle');
        }
    }

    /**
     * @param string $sizeAttribute
     * @param string $positionAttribute
     */
    protected function validateDimensions($sizeAttribute, $positionAttribute)
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

    /**
     * @inheritDoc
     */
    public function beforeValidate(): bool
    {
        if (!$this->folder_id) {
            $folder = $this->getDefaultFolder();
            $this->populateFolderRelation($folder);
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

        // Make sure width and height are not set to zero by cropping reset.
        foreach (['width', 'height'] as $attribute) {
            if (!$this->getAttribute($attribute)) {
                $this->setAttribute($attribute, $this->getOldAttribute($attribute));
            }
        }

        // Sanitize basename.
        $this->basename = trim(preg_replace('#/{2,}#', '/', trim($this->basename, '/')));
        $this->basename = preg_replace('#[^_a-zA-Z0-9/\-@]+#', '', $this->basename);

        return parent::beforeValidate();
    }

    /**
     * Tries to delete file on error.
     */
    public function afterValidate()
    {
        if ($this->hasErrors()) {
            if ($this->upload) {
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

    /**
     * @inheritDoc
     */
    public function beforeSave($insert)
    {
        $this->attachBehaviors([
            'BlameableBehavior' => 'davidhirtz\yii2\skeleton\behaviors\BlameableBehavior',
            'TimestampBehavior' => 'davidhirtz\yii2\skeleton\behaviors\TimestampBehavior',
        ]);

        if (!$insert) {
            // Makes sure filename is changed on image resize or rotation to bust cache.
            if ($this->isTransformableImage() && $this->hasChangedDimensions()) {
                if (!$this->isAttributeChanged('basename')) {
                    $this->basename = preg_replace('/@\d+(x\d+)?$/', '', $this->basename) . ($this->angle ? "@{$this->angle}" : "@{$this->width}x{$this->height}");
                }
            }
        }

        return parent::beforeSave($insert);
    }

    /**
     * @inheritDoc
     */
    public function afterSave($insert, $changedAttributes)
    {
        // Prevents timeouts on file manipulations and writes to remote disks.
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        // Get old folder and name to apply changes to file system
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
            if (array_key_exists('folder_id', $changedAttributes) && $folder) {
                $folder->recalculateFileCount();
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
                    $changedAttributes[$attribute] = $changedAttributes[$attribute] ?? $prevAttributes[$attribute];
                }
            }
        }

        if (array_key_exists('folder_id', $changedAttributes)) {
            $this->folder->recalculateFileCount();
        }

        // Run parent events then remove upload from memory
        parent::afterSave($insert, $changedAttributes);
        $this->upload = null;
    }

    /**
     * Delete assets to trigger their afterDelete clean up, related methods can check
     * for {@link \davidhirtz\yii2\media\models\File::isDeleted()} to prevent unnecessary updates.
     *
     * @return bool
     */
    public function beforeDelete()
    {
        if (parent::beforeDelete()) {
            foreach ($this->getAssetModels() as $model) {
                $assets = $model::find()->where(['file_id' => $this->id])->all();
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


    /**
     * @inheritDoc
     */
    public function afterDelete()
    {
        if ($this->folder) {
            FileHelper::unlink($this->getFilePath());
            $this->folder->recalculateFileCount();
        }

        parent::afterDelete();
    }

    /**
     * Sets an upload via chunked upload file.
     * @return bool
     */
    public function upload(): bool
    {
        $this->upload = ChunkedUploadedFile::getInstance($this, 'upload');
        return $this->upload && !$this->upload->isPartial();
    }

    /**
     * Loads an upload via url or filepath.
     * @param string $url
     * @return bool
     */
    public function copy($url): bool
    {
        $this->upload = new StreamUploadedFile(['url' => $url]);
        return !$this->upload->getHasError();
    }

    /**
     * Duplicates a file suppressing any upload errors.
     * @param array $attributes
     * @return $this
     */
    public function clone($attributes = [])
    {
        $clone = new static();
        $clone->setAttributes(array_merge($this->getAttributes(), $attributes));
        $clone->populateRelation('folder', $this->folder);
        $clone->copy($this->getFilePath());
        $clone->insert();

        return $clone;
    }

    /**
     * @param int|null $folder
     * @param string|null $basename
     */
    public function deleteTransformations($folder = null, $basename = null)
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

    /**
     * Saves the uploaded file.
     */
    protected function saveUploadedFile(): void
    {
        FileHelper::createDirectory(dirname($this->getFilePath()));
        $this->upload->saveAs($this->getFilePath());
    }

    /**
     * Updates image to stay in `maxWidth` and/or `maxHeight constrains.
     */
    protected function resizeImage()
    {
        $image = Image::resize($this->getFilePath(), $this->maxWidth, $this->maxHeight);
        $this->updateImageInternal($image);
    }

    /**
     * Crops image to fit given image `width` and `height` from coordinates.
     */
    protected function cropImage()
    {
        $image = Image::crop($this->getFilePath(), $this->width, $this->height, [$this->x, $this->y]);
        $this->updateImageInternal($image);
    }

    /**
     * Rotates image by given `angle`.
     */
    protected function rotateImage()
    {
        $image = Image::rotate($this->getFilePath(), $this->angle);
        $this->updateImageInternal($image);
    }

    /**
     * Saves image and updates image attributes if necessary.
     *
     * @param ImageInterface $image
     */
    protected function updateImageInternal($image)
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
     * @return ActiveQuery
     */
    public function getFolder(): ActiveQuery
    {
        return $this->hasOne(Folder::class, ['id' => 'folder_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getTransformations(): ActiveQuery
    {
        return $this->hasMany(Transformation::class, ['file_id' => 'id'])
            ->inverseOf('file');
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
     * @param Folder $folder
     */
    public function populateFolderRelation($folder)
    {
        $this->populateRelation('folder', $folder);
        $this->folder_id = $folder->id;
    }

    /**
     * @return FileQuery
     */
    public static function find()
    {
        return new FileQuery(get_called_class());
    }

    /**
     * @return Folder
     */
    protected function getDefaultFolder()
    {
        return Folder::getDefault();
    }

    /**
     * Recalculates transformation count.
     * @return $this
     */
    public function recalculateTransformationCount()
    {
        $this->transformation_count = $this->getTransformations()->count();
        return $this;
    }

    /**
     * @return AssetInterface[]
     */
    public function getAssetModels()
    {
        $assets = [];
        $this->_assetCount = 0;

        foreach (static::getModule()->assets as $asset) {
            /** @var AssetInterface $asset */
            $asset = Yii::createObject(is_array($asset) ? $asset['class'] : $asset);
            if ($assetCount = $this->getAttribute($asset->getFileCountAttribute())) {
                $this->_assetCount += $assetCount;
                $assets[] = $asset;
            }
        }

        return $assets;
    }

    /**
     * @return int
     */
    public function getAssetCount(): int
    {
        if ($this->_assetCount === null) {
            $this->getAssetModels();
        }

        return $this->_assetCount;
    }

    /**
     * @return array
     */
    public function getTrailAttributes(): array
    {
        return array_diff($this->attributes(), [
            'transformation_count',
            'cms_asset_count',
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
        return Yii::t('media', 'File');
    }

    /**
     * @return array|false
     */
    public function getTrailModelAdminRoute()
    {
        return $this->id ? ['/admin/file/update', 'id' => $this->id] : false;
    }

    /**
     * @param array|string|null $transformations
     * @param string|null $extension
     * @return array|string
     */
    public function getSrcset($transformations = null, $extension = null)
    {
        $transformations = is_string($transformations) ? [$transformations] : $transformations;
        $srcset = [];

        if ($this->isTransformableImage()) {
            foreach ($transformations as $name) {
                if ($url = $this->getTransformationUrl($name, $extension)) {
                    $option = $this->getTransformationOptions($name);
                    $width = $option['width'] ?? (isset($option['height']) ? floor($option['height'] / $this->height * $this->width) : $this->width);
                    $srcset[$width] = $url;
                }
            }
        }

        return $srcset ? $srcset : $this->getUrl();
    }

    /**
     * @return bool|float
     */
    public function getHeightPercentage()
    {
        return $this->height && $this->width ? round($this->height / $this->width * 100, 2) : false;
    }

    /**
     * @param string $filename
     * @return string
     */
    public function humanizeFilename($filename): string
    {
        return StringHelper::mb_ucfirst(str_replace(['.', '_', '-'], ' ', (pathinfo($filename, PATHINFO_FILENAME))));
    }

    /**
     * @return string
     */
    public function getDimensions(): string
    {
        return $this->hasDimensions() ? ($this->width . ' x ' . $this->height) : '';
    }

    /**
     * @return bool
     */
    public function hasPreview(): bool
    {
        return in_array($this->extension, ['bmp', 'gif', 'jpg', 'jpeg', 'png', 'svg', 'webp']);
    }

    /**
     * @return bool
     */
    public function hasDimensions(): bool
    {
        return $this->width && $this->height;
    }

    /**
     * @return bool
     */
    public function isTransformableImage(): bool
    {
        return in_array($this->extension, ['gif', 'jpg', 'jpeg', 'png']) && $this->hasDimensions();
    }

    /**
     * @param string $name
     * @return bool
     */
    public function isValidTransformation($name)
    {
        if ($this->isTransformableImage()) {
            if ($transformation = $this->getTransformationOptions($name)) {
                if (empty($transformation['scaleUp'])) {
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

    /**
     * @return bool
     */
    public function hasChangedDimensions(): bool
    {
        return $this->isAttributeChanged('width') || $this->isAttributeChanged('height') || $this->angle;
    }

    /**
     * @param string $name
     * @param string|null $key
     * @return mixed
     */
    public function getTransformationOptions($name, $key = null)
    {
        $module = static::getModule();
        return $key ? ($module->transformations[$name][$key] ?? null) : $module->transformations[$name];
    }

    /**
     * @param string $name
     * @param string|null $extension
     * @return string|null
     */
    public function getTransformationUrl($name, $extension = null)
    {
        if ($this->isValidTransformation($name)) {
            return $this->folder->getUploadUrl() . $name . '/' . $this->basename . '.' . ($extension ?: $this->extension);
        }

        return null;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->folder->getUploadUrl() . $this->getFilename();
    }

    /**
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->folder->getUploadPath() . $this->getFilename();
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->basename . '.' . $this->extension;
    }

    /**
     * @return FileActiveForm
     */
    public function getActiveForm()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return FileActiveForm::class;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            'folder_id' => Yii::t('media', 'Folder'),
            'basename' => Yii::t('media', 'Filename'),
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