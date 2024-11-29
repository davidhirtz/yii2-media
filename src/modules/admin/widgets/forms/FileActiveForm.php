<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\forms;

use davidhirtz\yii2\media\assets\CropperJsAsset;
use davidhirtz\yii2\media\models\collections\FolderCollection;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\Module;
use davidhirtz\yii2\media\modules\admin\widgets\forms\fields\FilePreview;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\helpers\ArrayHelper;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\modules\admin\widgets\forms\traits\ModelTimestampTrait;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveField;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveForm;
use Yii;

/**
 * @property File $model
 */
class FileActiveForm extends ActiveForm
{
    use ModelTimestampTrait;
    use ModuleTrait;

    protected array $cropAttributeNames = ['width', 'height', 'x', 'y'];

    public bool $hasStickyButtons = true;

    public function init(): void
    {
        $this->fields ??= [
            'folder_id',
            'name',
            'basename',
            'alt_text',
            'angle',
        ];

        $this->buttons ??= [
            $this->button(),
            Html::tag('div', Yii::t('media', 'Clear Selection'), [
                'id' => 'image-clear',
                'class' => 'btn btn-secondary show-on-crop-end',
                'style' => 'display:none',
            ]),
        ];

        if ($this->isTransformableImage()) {
            $this->registerCropClientScript();
        }

        parent::init();
    }

    public function renderHeader(): void
    {
        echo $this->previewField();
        echo $this->horizontalLine();
    }

    public function renderFields(): void
    {
        parent::renderFields();
        $this->renderExtraFields();
    }

    public function renderExtraFields(): void
    {
        if ($this->isTransformableImage()) {
            echo $this->cropField();
        }

        echo $this->dimensionsField();
        echo $this->sizeField();
    }

    /**
     * This method uses old attributes for basename and sizes as they would only differ on an error in which case the
     * new attributes might not be accurate.
     */
    public function previewField(): string
    {
        $model = clone $this->model;
        $model->setAttributes($this->model->getOldAttributes(), false);

        $html = FilePreview::widget(['file' => $model]);
        return $html ? $this->row($this->offset($html)) : '';
    }

    /**
     * @noinspection PhpUnused {@see static::$fields}
     */
    public function basenameField(): ActiveField|string
    {
        return $this->field($this->model, 'basename')->appendInput('.' . $this->model->extension);
    }

    /**
     * @noinspection PhpUnused {@see static::$fields}
     */
    public function folderIdField(): ActiveField|string
    {
        $folders = FolderCollection::getAll();
        return count($folders) > 1
            ? $this->field($this->model, 'folder_id')->dropDownList(ArrayHelper::getColumn($folders, 'name'))
            : '';
    }


    /**
     * @noinspection PhpUnused {@see static::$fields}
     */
    public function angleField(): ActiveField|string
    {
        if ($this->model->isTransformableImage()) {
            if ($options = $this->getAngleOptions()) {
                return $this->field($this->model, 'angle')->dropDownList($options, ['prompt' => '']);
            }
        }

        return '';
    }

    /**
     * @noinspection PhpUnused {@see static::$fields}
     */
    public function actionAltText(?array $options = []): string
    {
        if ($this->model->hasPreview()) {
            return '';
        }

        return $this->field($this->model, 'alt_text', $options);
    }

    public function dimensionsField(): ActiveField|string
    {
        return $this->model->hasDimensions()
            ? $this->plainTextRow($this->model->getAttributeLabel('dimensions'), $this->model->getDimensions())
            : '';
    }

    public function sizeField(): ActiveField|string
    {
        return $this->model->size
            ? $this->plainTextRow($this->model->getAttributeLabel('size'), Yii::$app->getFormatter()->asShortSize($this->model->size, 2))
            : '';
    }

    public function cropField(): ActiveField|string
    {
        $fields = [];

        if ($ratios = $this->getRatioOptions()) {
            $options = [
                'id' => 'image-ratio',
                'class' => 'form-control',
            ];

            $fields[] = $this->labelRow(Yii::t('media', 'Aspect ratio'), Html::dropDownList('', null, $ratios, $options), [
                'class' => 'show-on-crop-end',
                'style' => 'display:none',
            ]);
        }

        foreach ($this->cropAttributeNames as $attribute) {
            $fields[] = Html::activeHiddenInput($this->model, $attribute, ['id' => 'image-' . $attribute]);
        }

        return implode('', $fields);
    }

    protected function getAngleOptions(): array|false
    {
        return [
            180 => Yii::t('media', '180°'),
            90 => Yii::t('media', '90° Clockwise'),
            -90 => Yii::t('media', '90° Counter Clockwise'),
        ];
    }

    protected function getRatioOptions(): array|false
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('admin')->getModule('media');
        return $module->cropRatios;
    }

    public function isTransformableImage(): bool
    {
        return $this->model->isTransformableImage() && array_intersect($this->cropAttributeNames, $this->model->safeAttributes());
    }

    public function registerCropClientScript(): void
    {
        CropperJsAsset::register($view = $this->getView());
        $view->registerJs('Skeleton.registerImageCrop("image")');
    }
}
