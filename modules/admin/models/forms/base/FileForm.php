<?php

namespace davidhirtz\yii2\media\modules\admin\models\forms\base;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Transformation;
use davidhirtz\yii2\media\modules\admin\models\forms\FolderForm;
use davidhirtz\yii2\skeleton\db\ActiveQuery;
use davidhirtz\yii2\skeleton\helpers\FileHelper;
use davidhirtz\yii2\skeleton\web\ChunkedUploadedFile;
use Yii;
use yii\helpers\StringHelper;

/**
 * Class FileForm
 * @package davidhirtz\yii2\media\modules\admin\models\forms\base
 *
 * @method static \davidhirtz\yii2\media\modules\admin\models\forms\FileForm findOne($condition)
 */
class FileForm extends File
{
    /**
     * @var ChunkedUploadedFile
     */
    public $upload;

    /**
     * @return array
     */
    public function rules(): array
    {
        $module = static::getModule();

        return array_merge(parent::rules(), [
            [
                ['upload'],
                'file',
                'extensions' => $module->allowedExtensions,
                'checkExtensionByMimeType' => $module->checkExtensionByMimeType,
                'skipOnEmpty' => !$this->getIsNewRecord(),
            ],
        ]);
    }

    /**
     * @return bool
     */
    public function beforeValidate(): bool
    {
        $this->upload = ChunkedUploadedFile::getInstance($this, 'upload');

        if ($this->upload) {
            if (!$this->name) {
                $this->name = $this->humanizeFilename($this->upload->name);
            }

            $this->basename = !static::getModule()->keepFilename ? FileHelper::generateRandomFilename() : $this->upload->getBaseName();
            $this->extension = $this->upload->getExtension();
            $this->size = $this->upload->size;

            if ($size = getimagesize($this->upload->tempName)) {
                $this->width = $size[0];
                $this->height = $size[1];
            }
        }

        return parent::beforeValidate();
    }

    /**
     * @inheritdoc
     */
    public function afterSave($insert, $changedAttributes)
    {
        if ($this->upload) {
            if (!$insert) {
                $folder = array_key_exists('folder_id', $changedAttributes) ? FolderForm::findOne($changedAttributes['folder_id']) : $this->folder;
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

            $this->upload->saveAs($this->folder->getUploadPath() . $this->getFilename());
        }

        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * @return ActiveQuery
     */
    public function getFolder(): ActiveQuery
    {
        return $this->hasOne(FolderForm::class, ['id' => 'folder_id']);
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
    public function getDimensions()
    {
        return $this->width . ' x ' . $this->height;
    }

    /**
     * @return array
     */
    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            'dimensions' => Yii::t('media', 'Dimensions'),
            'size' => Yii::t('media', 'Size'),
            'upload' => Yii::t('media', 'Replace file'),
        ]);
    }
}