<?php

namespace davidhirtz\yii2\media\modules\admin\models\forms\base;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\models\forms\FolderForm;
use davidhirtz\yii2\skeleton\db\ActiveQuery;
use davidhirtz\yii2\skeleton\web\ChunkedUploadedFile;
use yii\helpers\FileHelper;
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
     * @var
     */
    public $checkExtensionByMimeType = false;

    /**
     * @return array
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [
                ['upload'],
                'file',
                'extensions' => $this->allowedExtensions,
                'checkExtensionByMimeType' => $this->checkExtensionByMimeType,
                'skipOnEmpty' => false,
                'when' => function(){
                    return $this->getIsNewRecord();
                }
            ],
        ]);
    }

    /**
     * @return bool
     */
    public function beforeValidate(): bool
    {
        if ($this->getIsNewRecord()) {
            $this->upload = ChunkedUploadedFile::getInstance($this, 'upload');

            if ($this->upload) {
                $this->name = $this->humanizeFilename($this->upload->name);
                $this->filename = $this->upload->name;
                $this->type = FileHelper::getMimeType($this->upload->tempName, null, false);
                $this->size = $this->upload->size;

                if ($size = getimagesize($this->upload->tempName)) {
                    $this->width = $size[0];
                    $this->height = $size[1];
                }
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
            $this->upload->saveAs($this->folder->getUploadPath() . $this->filename);
            $this->upload = null;
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
}