<?php

namespace davidhirtz\yii2\media\models\base;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\models\queries\FileQuery;
use davidhirtz\yii2\datetime\DateTime;
use davidhirtz\yii2\media\models\Transformation;
use davidhirtz\yii2\media\modules\admin\models\forms\FolderForm;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\db\ActiveQuery;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use davidhirtz\yii2\skeleton\db\StatusAttributeTrait;
use davidhirtz\yii2\skeleton\helpers\FileHelper;
use davidhirtz\yii2\skeleton\models\queries\UserQuery;
use davidhirtz\yii2\skeleton\models\User;
use Yii;

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

                if (!$module->keepFilename && $this->getIsNewRecord()) {
                    $this->basename = FileHelper::generateRandomFilename();
                }

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
        if (!$this->folder_id) {

            $folder = FolderForm::find()
                ->where('[[parent_id]] IS NULL')
                ->orderBy(['position' => SORT_ASC])
                ->one();

            if (!$folder) {
                $folder = new FolderForm;
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
        if ($insert) {
            $this->folder->recalculateFileCount();
        }

        // Use empty here so new uploads won't be renamed.
        if (!empty($changedAttributes['folder_id']) || !empty($changedAttributes['basename'])) {

            $folder = !empty($changedAttributes['folder_id']) ? FolderForm::findOne($changedAttributes['folder_id']) : $this->folder;
            $basename = !empty($changedAttributes['basename']) ? $changedAttributes['basename'] : $this->basename;

            if ($this->transformation_count) {
                foreach ($this->transformations as $transformation) {
                    FileHelper::createDirectory($this->folder->getUploadPath());
                    rename($folder->getUploadPath() . $transformation->name . DIRECTORY_SEPARATOR . $basename . '.' . $this->extension, $transformation->getFilePath());
                }
            }

            rename($folder->getUploadPath() . $basename . '.' . $this->extension, $this->folder->getUploadPath() . $this->getFilename());
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
     * @return bool
     */
    public function hasPreview()
    {
        return in_array($this->extension, ['bmp', 'gif', 'jpg', 'jpeg', 'png', 'svg', 'webp']);
    }

    /**
     * @return bool
     */
    public function isTransformableImage()
    {
        return in_array($this->extension, ['gif', 'jpg', 'jpeg', 'png']) && $this->width && $this->height;
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
     * @return array|null
     */
    public function getTransformationOptions($name)
    {
        $module = static::getModule();
        return isset($module->transformations[$name]) ? $module->transformations[$name] : null;
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function getTransformationUrl($name)
    {
        if ($this->isValidTransformation($name)) {
            return $this->folder->getUploadUrl() . $name . '/' . $this->getFilename();
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
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            'folder_id' => Yii::t('media', 'Folder'),
            'basename' => Yii::t('media', 'Filename'),
            'transformation_count' => Yii::t('media', 'Transformations'),
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