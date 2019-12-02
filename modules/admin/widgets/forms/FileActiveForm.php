<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\forms;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\widgets\FolderDropdownTrait;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveForm;
use Yii;
use yii\helpers\Html;

/**
 * Class FileActiveForm.
 * @package davidhirtz\yii2\media\modules\admin\widgets\forms
 *
 * @property File $model
 */
class FileActiveForm extends ActiveForm
{
    use FolderDropdownTrait;

    /**
     * @var bool
     */
    public $showUnsafeAttributes = true;

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (!$this->fields) {
            $this->fields = [
                'thumbnail',
                '-',
                'folder_id',
                'name',
                'basename',
                'dimensions',
                'size',
            ];
        }

        parent::init();
    }

    /**
     * @return string
     */
    public function thumbnailField()
    {
        return $this->model->hasPreview() ? $this->row($this->offset(Html::img($this->model->folder->getUploadUrl() . $this->model->getFilename(), ['class' => 'img-transparent']))) : '';
    }

    /**
     * @return \davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveField|string|\yii\widgets\ActiveField
     */
    public function folderIdField()
    {
        return count($folders = $this->getFolders()) > 1 ? $this->field($this->model, 'folder_id')->dropdownList($folders) : '';
    }

    /**
     * @return \davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveField
     */
    public function basenameField()
    {
        return $this->field($this->model, 'basename')->appendInput('.' . $this->model->extension);
    }

    /**
     * @return \davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveField
     */
    public function dimensionsField()
    {
        return $this->model->hasDimensions() ? $this->field($this->model, 'dimensions')->textInput(['readonly' => true, 'class' => 'form-control-plaintext']) : '';
    }

    /**
     * @return \davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveField
     */
    public function sizeField()
    {
        return $this->field($this->model, 'size')->textInput([
            'value' => Yii::$app->getFormatter()->asShortSize($this->model->size, 2),
            'readonly' => true,
            'class' => 'form-control-plaintext',
        ]);
    }
}