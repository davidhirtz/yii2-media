<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\forms;

use davidhirtz\yii2\media\assets\JQueryCropperAsset;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\widgets\FolderDropdownTrait;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveForm;
use Yii;

/**
 * Class FileActiveForm.
 * @package davidhirtz\yii2\media\modules\admin\widgets\forms
 *
 * @property File $model
 */
class FileActiveForm extends ActiveForm
{
    use FolderDropdownTrait, ModuleTrait;

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
                ['alt_text', ['visible' => $this->model->hasPreview()]],
                'dimensions',
                'size',
            ];
        }

        if (!$this->buttons) {
            $this->buttons = [
                $this->button(),
                Html::tag('div', Yii::t('media', 'Clear Selection'), [
                    'id' => 'image-clear',
                    'class' => 'btn btn-secondary',
                    'style' => 'display:none',
                ]),
            ];
        }

        if ($this->model->isTransformableImage()) {
            $this->registerCropClientScript();
            $this->fields[] = 'crop';
        }

        parent::init();
    }

    /**
     * @return string
     */
    public function thumbnailField()
    {
        if ($this->model->hasPreview()) {
            $image = Html::img($this->model->folder->getUploadUrl() . $this->model->getFilename(), [
                'id' => 'image',
                'class' => 'img-transparent',
            ]);

            if ($this->model->width) {
                $image = Html::tag('div', $image, ['style' => 'max-width:' . $this->model->width . 'px']);
            }

            return $this->row($this->offset($image));
        }

        return '';
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

    /**
     * @return string
     */
    public function cropField(): string
    {
        $fields = [];

        foreach (['width', 'height', 'x', 'y'] as $attribute) {
            $fields[] = Html::activeHiddenInput($this->model, $attribute, ['id' => 'image-' . $attribute]);
        }

        return implode('', $fields);
    }

    /**
     * @inheritDoc
     */
    public function registerCropClientScript()
    {
        JQueryCropperAsset::register($view = $this->getView());
        $view->registerJs('Skeleton.registerImageCrop("image")');
    }
}