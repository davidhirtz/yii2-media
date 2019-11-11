<?php

namespace davidhirtz\yii2\media\models\base;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\models\queries\FileQuery;
use davidhirtz\yii2\datetime\DateTime;
use davidhirtz\yii2\media\models\Transformation;
use davidhirtz\yii2\media\modules\admin\widgets\forms\FileActiveForm;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\db\ActiveQuery;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use davidhirtz\yii2\skeleton\db\StatusAttributeTrait;
use davidhirtz\yii2\skeleton\helpers\FileHelper;
use davidhirtz\yii2\skeleton\helpers\Image;
use davidhirtz\yii2\skeleton\models\queries\UserQuery;
use davidhirtz\yii2\skeleton\models\User;
use davidhirtz\yii2\skeleton\web\ChunkedUploadedFile;
use Yii;
use yii\base\Widget;
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
 * @property int $transformation_count
 * @property int $updated_by_user_id
 * @property DateTime $updated_at
 * @property DateTime $created_at
 * @property User $updated
 * @property Transformation[] $transformations
 * @property Folder $folder
 * @method static File findOne($condition)
 */
class File extends ActiveRecord
{
    use StatusAttributeTrait, ModuleTrait;

    /**
     * @var ChunkedUploadedFile
     */
    public $upload;

    /**
     * Constants.
     */
    const STATUS_DELETED = -1;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [
                ['folder_id', 'name', 'basename', 'extension'],
                'required',
            ],
            [
                ['status'],
                'validateStatus',
            ],
            [
                ['folder_id'],
                'filter',
                'filter' => 'intval',
            ],
            [
                ['folder_id'],
                'validateFolderId',
            ],
            [
                ['upload'],
                'file',
                'extensions' => static::getModule()->allowedExtensions,
                'checkExtensionByMimeType' => static::getModule()->checkExtensionByMimeType,
                'skipOnEmpty' => !$this->getIsNewRecord(),
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

                while (is_file($this->folder->getUploadPath() . $this->getFilename())) {

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
     * @inheritdoc
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

        $this->basename = preg_replace('/\s+/', '_', $this->basename);

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

        return parent::beforeSave($insert);
    }

    /**
     * @inheritdoc
     */
    public function afterSave($insert, $changedAttributes)
    {
        if ($this->upload) {
            if (!$insert) {
                $folder = array_key_exists('folder_id', $changedAttributes) ? Folder::findOne($changedAttributes['folder_id']) : $this->folder;
                $basename = array_key_exists('basename', $changedAttributes) ? $changedAttributes['basename'] : $this->basename;
                $extension = array_key_exists('extension', $changedAttributes) ? $changedAttributes['extension'] : $this->extension;

                if ($this->transformation_count) {
                    foreach ($this->transformations as $transformation) {
                        @unlink($folder->getUploadPath() . $transformation->name . DIRECTORY_SEPARATOR . $basename . '.' . $extension);
                    }

                    // There is no need to update "transformation_count" as recalculateTransformationCount is called
                    // with the next page reload on "admin" transformation.
                    Transformation::deleteAll(['file_id' => $this->id]);
                }

                @unlink($folder->getUploadPath() . $basename . '.' . $extension);

                // Unset folder_id otherwise parent method will try to move the file again.
                if (array_key_exists('folder_id', $changedAttributes)) {
                    unset($changedAttributes['folder_id']);
                }

                // Unset basename otherwise parent method will try to rename the file again.
                if (array_key_exists('basename', $changedAttributes)) {
                    unset($changedAttributes['basename']);
                }
            }

            FileHelper::createDirectory($this->folder->getUploadPath());
            $this->upload->saveAs($this->folder->getUploadPath() . $this->getFilename());
        }

        if (!$insert) {
            if (array_key_exists('folder_id', $changedAttributes) || array_key_exists('basename', $changedAttributes)) {
                $basename = !empty($changedAttributes['basename']) ? $changedAttributes['basename'] : $this->basename;
                $folder = $this->folder;

                if (!empty($changedAttributes['folder_id'])) {
                    $folder = Folder::findOne($changedAttributes['folder_id']);
                    $folder->recalculateFileCount();
                }

                FileHelper::createDirectory($this->folder->getUploadPath());

                if ($this->transformation_count) {
                    foreach ($this->transformations as $transformation) {
                        $transformationBasename = $folder->getUploadPath() . $transformation->name . DIRECTORY_SEPARATOR . $basename;
                        @rename($transformationBasename . '.' . $this->extension, $transformation->getFilePath());
                        @rename($transformationBasename . '.webp', $transformation->getFilePath('webp'));
                    }
                }

                @rename($folder->getUploadPath() . $basename . '.' . $this->extension, $this->folder->getUploadPath() . $this->getFilename());
            }
        }

        if (array_key_exists('folder_id', $changedAttributes)) {
            $this->folder->recalculateFileCount();
        }

        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * Delete relations to trigger their afterDelete clean up, related methods can check
     * for File::isDeleted() to prevent unnecessary updates.
     * @return bool
     */
    public function beforeDelete()
    {
        $this->status = static::STATUS_DELETED;

        foreach (static::getModule()->relations as $relation) {
            /** @var ActiveRecord $model */
            $model = is_array($relation) ? $relation['class'] : $relation;
            $models = $model::find()->where(['file_id' => $this->id])->all();

            foreach ($models as $model) {
                $model->populateRelation('file', $this);
                $model->delete();
            }
        }

        if ($this->folder && $this->transformation_count) {
            foreach ($this->transformations as $transformation) {
                @unlink($transformation->getFilePath());
                @unlink($transformation->getFilePath('webp'));
            }
        }

        return parent::beforeDelete();
    }


    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        if ($this->folder) {
            @unlink($this->folder->getUploadPath() . $this->getFilename());
            $this->folder->recalculateFileCount();
        }

        parent::afterDelete();
    }

    /**
     * Sets the upload via chunked upload file.
     */
    public function upload()
    {
        $this->upload = ChunkedUploadedFile::getInstance($this, 'upload');
        return $this->upload && !$this->upload->isPartial();
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
            ->indexBy('name')
            ->inverseOf('file');
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
    public function getUrl()
    {
        return $this->folder->getUploadUrl() . $this->getFilename();
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->basename . '.' . $this->extension;
    }

    /**
     * @return bool
     */
    public function isDeleted()
    {
        return $this->status == static::STATUS_DELETED;
    }

    /**
     * @return FileActiveForm|Widget
     */
    public function getActiveForm()
    {
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
            'upload' => Yii::t('media', 'Replace file'),
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