<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\forms;

use davidhirtz\yii2\media\assets\JQueryCropperAsset;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\widgets\FolderDropdownTrait;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveField;
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
    use FolderDropdownTrait;
    use ModuleTrait;

    /**
     * @var bool
     */
    public $hasStickyButtons = true;

    /**
     * @var string[]
     */
    protected $cropAttributeNames = ['width', 'height', 'x', 'y'];

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (!$this->fields) {
            $this->fields = [
                'folder_id',
                'name',
                'basename',
                ['alt_text', ['visible' => $this->model->hasPreview()]],
                'angle',
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

        if ($this->isTransformableImage()) {
            $this->registerCropClientScript();
        }

        parent::init();
    }

    /**
     * Renders thumbnail field.
     */
    public function renderHeader()
    {
        echo $this->thumbnailField();
        echo $this->horizontalLine();
    }

    /**
     * Adds extra file fields.
     */
    public function renderFields()
    {
        parent::renderFields();
        $this->renderExtraFields();
    }

    /**
     * Renders extra file fields.
     */
    public function renderExtraFields()
    {
        echo $this->dimensionsField();
        echo $this->sizeField();

        if ($this->isTransformableImage()) {
            echo $this->cropField();
        }
    }

    /**
     * This method uses old attributes for basename and sizes as they would only differ on an error in which case
     * the new attributes might not be accurate.
     *
     * @return string
     */
    public function thumbnailField()
    {
        if ($this->model->hasPreview()) {
            $image = Html::img($this->model->folder->getUploadUrl() . $this->model->getOldAttribute('basename') . '.' . $this->model->extension, [
                'id' => 'image',
                'class' => 'img-transparent',
            ]);

            if ($width = $this->model->getOldAttribute('width')) {
                $image = Html::tag('div', $image, ['style' => 'max-width:' . $width . 'px']);
            }

            return $this->row($this->offset($image));
        }

        return '';
    }

    /**
     * @return ActiveField|string|\yii\widgets\ActiveField
     */
    public function folderIdField()
    {
        return count($folders = $this->getFolders()) > 1 ? $this->field($this->model, 'folder_id')->dropDownList($folders) : '';
    }

    /**
     * @return ActiveField
     */
    public function basenameField()
    {
        return $this->field($this->model, 'basename')->appendInput('.' . $this->model->extension);
    }

    /**
     * @return ActiveField|string
     */
    public function angleField()
    {
        if ($this->model->isTransformableImage()) {
            if ($options = $this->getAngleOptions()) {
                return $this->field($this->model, 'angle')->dropDownList($options, ['prompt' => '']);
            }
        }

        return '';
    }

    /**
     * @return array|false
     */
    public function getAngleOptions()
    {
        return [
            180 => Yii::t('media', '180°'),
            90 => Yii::t('media', '90° Clockwise'),
            -90 => Yii::t('media', '90° Counter Clockwise'),
        ];
    }

    /**
     * @return ActiveField|string
     */
    public function dimensionsField()
    {
        return $this->model->hasDimensions() ? $this->plainTextRow($this->model->getAttributeLabel('dimensions'), $this->model->getDimensions()) : '';
    }

    /**
     * @return ActiveField|string
     */
    public function sizeField()
    {
        return $this->model->size ? $this->plainTextRow($this->model->getAttributeLabel('size'), Yii::$app->getFormatter()->asShortSize($this->model->size, 2)) : '';
    }

    /**
     * @return string
     */
    public function cropField(): string
    {
        $fields = [];

        foreach ($this->cropAttributeNames as $attribute) {
            $fields[] = Html::activeHiddenInput($this->model, $attribute, ['id' => 'image-' . $attribute]);
        }

        return implode('', $fields);
    }

    /**
     * @return bool whether the user can transform this image.
     */
    public function isTransformableImage(): bool
    {
        return $this->model->isTransformableImage() && array_intersect($this->cropAttributeNames, $this->model->safeAttributes());
    }

    /**
     * Registers crop.
     */
    public function registerCropClientScript()
    {
        JQueryCropperAsset::register($view = $this->getView());
        $view->registerJs('Skeleton.registerImageCrop("image")');
    }
}