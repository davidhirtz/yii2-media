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
use Yii;
use yii\helpers\StringHelper;

/**
 * Class File.
 * @package davidhirtz\yii2\media\models\base
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
 * @method static File findOne($condition)
 */
class File extends ActiveRecord
{
    use I18nAttributesTrait, StatusAttributeTrait, ModuleTrait;

    /**
     * @var ChunkedUploadedFile|StreamUploadedFile
     */
    public $upload;

    /**
     * @var int x-position for cropped image, leave empty to crop from center
     */
    public $x;

    /**
     * @var int y-position for cropped image, leave empty to crop from center
     */
    public $y;

    /**
     * @var int
     */
    private $_assetCount;

    /**
     * @var string
     */
    private $resource;

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
                'validateStatus',
            ],
            [
                ['folder_id', 'width', 'height', 'x', 'y'],
                'filter',
                'filter' => 'intval',
            ],
            [
                ['folder_id'],
                'validateFolderId',
            ],
            [
                ['name', 'basename'],
                'filter',
                'filter' => 'trim',
            ],
            [
                ['name', 'basename'],
                'string',
                'max' => 250,
            ],
            [
                ['basename'],
                'validateFilename',
            ],
            [
                ['width'],
                'validateWidth',
            ],
            [
                ['height'],
                'validateHeight',
            ],
            [
                $this->getI18nAttributesNames(['alt_text']),
                'string',
                'max' => 250,
            ],
        ]);
    }

    /**
     * @see File::rules()
     */
    public function validateFolderId()
    {
        if (!$this->folder) {
            $this->addInvalidAttributeError('folder_id');
        }
    }

    /**
     * @see File::rules()
     */
    public function validateFilename()
    {
        if ($this->folder) {
            if ($this->getIsNewRecord() || $this->isAttributeChanged('basename')) {
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
    }

    /**
     * @inheritDoc
     */
    public function beforeValidate(): bool
    {
        if ($this->upload) {
            if (!$this->name) {
                $this->name = $this->humanizeFilename($this->upload->name);
            }

            $this->basename = !static::getModule()->keepFilename ? FileHelper::generateRandomFilename() : $this->upload->getBaseName();
            $this->extension = $this->upload->getExtension();
            $this->size = $this->upload->size;

            if ($size = Image::getImageSize($this->upload->tempName, $this->extension)) {
                $this->width = $size[0];
                $this->height = $size[1];
            }
        }

        if (!$this->folder_id) {
            $folder = Folder::find()
                ->where('[[parent_id]] IS NULL')
                ->orderBy(['position' => SORT_ASC])
                ->one();

            if (!$folder) {
                $folder = new Folder;
                $folder->name = Yii::t('media', 'Default');
                $folder->save();
            }

            $this->folder_id = $folder->id;
            $this->populateRelation('folder', $folder);
        }

        // Make sure width and height are not set to zero by cropping reset.
        foreach (['width', 'height'] as $attribute) {
            if (!$this->getAttribute($attribute)) {
                $this->setAttribute($attribute, $this->getOldAttribute($attribute));
            }
        }

        $this->basename = preg_replace('/\s+/', '_', $this->basename);
        return parent::beforeValidate();
    }

    /**
     * Tries to delete file on error.
     */
    public function afterValidate()
    {
        if ($this->hasErrors()) {
            @unlink($this->upload->tempName);
        }

        parent::afterValidate();
    }

    /**
     * @inheritDoc
     */
    public function beforeSave($insert)
    {
        if (!$insert) {
            // Make sure filename is changed on resize to bust cache.
            if (!$this->isAttributeChanged('basename') && ($this->isAttributeChanged('width') || $this->isAttributeChanged('height'))) {
                $this->basename = preg_replace('/(@\d+x\d+)$/', '', $this->basename) . "@{$this->width}x{$this->height}";
            }
        }

        $this->attachBehaviors([
            'BlameableBehavior' => 'davidhirtz\yii2\skeleton\behaviors\BlameableBehavior',
            'TimestampBehavior' => 'davidhirtz\yii2\skeleton\behaviors\TimestampBehavior',
        ]);

        return parent::beforeSave($insert);
    }

    /**
     * @inheritDoc
     */
    public function afterSave($insert, $changedAttributes)
    {
        if (!$insert) {
            $folder = array_key_exists('folder_id', $changedAttributes) ? Folder::findOne($changedAttributes['folder_id']) : $this->folder;
            $basename = array_key_exists('basename', $changedAttributes) ? $changedAttributes['basename'] : $this->basename;
            $extension = array_key_exists('extension', $changedAttributes) ? $changedAttributes['extension'] : $this->extension;
            $filepath = $folder->getUploadPath() . $basename . '.' . $extension;

            if ($this->upload) {
                // Delete old file and remove transformations.
                $this->deleteTransformations($folder, $basename);
                @unlink($filepath);

                // Unset folder_id otherwise parent method will try to move the file again.
                if (array_key_exists('folder_id', $changedAttributes)) {
                    unset($changedAttributes['folder_id']);
                }

                // Unset basename otherwise parent method will try to rename the file again.
                if (array_key_exists('basename', $changedAttributes)) {
                    unset($changedAttributes['basename']);
                }

            } elseif (array_key_exists('width', $changedAttributes) || array_key_exists('height', $changedAttributes)) {
                // Crop file.
                if ($this->isTransformableImage()) {
                    ini_set('memory_limit', '256M');
                    $image = Image::crop($filepath, $this->width, $this->height, [$this->x, $this->y]);
                    $image->save($filepath);
                    $this->deleteTransformations($folder, $basename);
                }
            }

            // Change name or folder.
            if (array_key_exists('folder_id', $changedAttributes) || array_key_exists('basename', $changedAttributes)) {
                $basename = !empty($changedAttributes['basename']) ? $changedAttributes['basename'] : $this->basename;
                $folder = $this->folder;

                if (!empty($changedAttributes['folder_id'])) {
                    $folder = Folder::findOne($changedAttributes['folder_id']);
                    $folder->recalculateFileCount();
                }

                FileHelper::createDirectory($this->folder->getUploadPath());
                @rename($filepath, $this->getFilePath());

                if ($this->transformation_count) {
                    foreach ($this->transformations as $transformation) {
                        FileHelper::createDirectory($transformation->getUploadPath());
                        $transformationBasename = $folder->getUploadPath() . $transformation->name . DIRECTORY_SEPARATOR . $basename;
                        @rename($transformationBasename . '.' . $transformation->extension, $transformation->getFilePath($transformation->extension));
                    }
                }
            }
        }

        if ($this->upload) {
            FileHelper::createDirectory($this->folder->getUploadPath());
            $this->upload->saveAs($this->getFilePath());
        }

        if (array_key_exists('folder_id', $changedAttributes)) {
            $this->folder->recalculateFileCount();
        }

        parent::afterSave($insert, $changedAttributes);
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
            @unlink($this->getFilePath());
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
        $clone = new static;
        $clone->setAttributes(array_merge($this->getAttributes(), $attributes));
        $clone->populateRelation('folder', $this->folder);
        $clone->copy($this->getFilePath());
        $clone->insert();

        return $clone;
    }

    /**
     * @param int $folder
     * @param string $basename
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
                @unlink($folder->getUploadPath() . $transformation->name . DIRECTORY_SEPARATOR . $basename . '.' . $transformation->extension);
            }

            if (!$this->isDeleted()) {
                // Transformations are deleted via database relation...
                Transformation::deleteAll(['file_id' => $this->id]);
                $this->updateAttributes(['transformation_count' => 0]);
            }
        }
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
     * @return FileQuery
     */
    public static function find(): FileQuery
    {
        return new FileQuery(get_called_class());
    }

    /**
     * Recalculates transformation count.
     */
    public function recalculateTransformationCount()
    {
        $this->transformation_count = $this->getTransformations()->count();
        $this->update(false);
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
     * @param array|string $transformations
     * @param string $extension
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
                return !empty($transformation['scaleUp']) || ((empty($transformation['width']) || $transformation['width'] <= $this->width) && (empty($transformation['height']) || $transformation['height'] <= $this->height));
            }
        }

        return false;
    }

    /**
     * @param string $name
     * @param string $key
     * @return mixed
     */
    public function getTransformationOptions($name, $key = null)
    {
        $module = static::getModule();
        return $key ? ($module->transformations[$name][$key] ?? null) : $module->transformations[$name];
    }

    /**
     * @param string $name
     * @param string $extension
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
            'size' => Yii::t('media', 'Size'),
            'alt_text' => Yii::t('media', 'Alt text'),
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