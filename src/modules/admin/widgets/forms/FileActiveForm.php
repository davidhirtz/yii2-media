<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\forms;

use davidhirtz\yii2\media\assets\ImageCropAsset;
use davidhirtz\yii2\media\models\collections\FolderCollection;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\Module;
use davidhirtz\yii2\media\modules\admin\widgets\forms\fields\FilePreview;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\helpers\ArrayHelper;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\html\Button;
use davidhirtz\yii2\skeleton\html\Div;
use davidhirtz\yii2\skeleton\modules\admin\widgets\forms\traits\ModelTimestampTrait;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveField;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ActiveForm;
use Override;
use Yii;

/**
 * @property File $model
 */
class FileActiveForm extends ActiveForm
{
    use ModelTimestampTrait;
    use ModuleTrait;

    public bool $hasStickyButtons = true;
    protected array $imageAttributeNames = ['width', 'height', 'x', 'y'];

    /**
     * @see self::folderIdField()
     * @see self::basenameField()
     * @see self::altTextField()
     * @see self::angleField()
     */
    #[Override]
    public function init(): void
    {
        $this->fields ??= [
            'folder_id',
            'name',
            'basename',
            'alt_text',
            'angle',
        ];

        $this->buttons = [
            Button::primary(Yii::t('skeleton', 'Update'))
                ->type('submit'),
        ];

        if ($this->isTransformableImage()) {
            $this->buttons = [
                ...$this->buttons,
                Button::secondary(Yii::t('media', 'Crop image'))
                    ->attribute('data-id', 'image-open'),
                Button::secondary(Yii::t('media', 'Cancel'))
                    ->attribute('data-id', 'image-cancel')
                    ->attribute('hidden', true),
            ];

            $this->registerClientScript();
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
        if ($this->isTransformableImage()) {
            echo $this->imageFields();
        }

        parent::renderFields();

        echo $this->dimensionsField();
        echo $this->sizeField();
    }

    /**
     * This method uses old attributes for basename and sizes as they would only differ on an error in which case the
     * new attributes might not be accurate.
     */
    public function previewField(): string
    {
        $file = clone $this->model;
        $file->setAttributes($this->model->getOldAttributes(), false);

        $html = (string)Yii::createObject(FilePreview::class, [
            'file' => $file,
            'attributes' => [
                'data-id' => 'image',
            ],
        ]);

        return $html ? $this->row($this->offset($html)) : '';
    }

    public function basenameField(): ActiveField|string
    {
        return $this->field($this->model, 'basename')->appendInput('.' . $this->model->extension);
    }

    public function folderIdField(): ActiveField|string
    {
        $folders = FolderCollection::getAll();
        return count($folders) > 1
            ? $this->field($this->model, 'folder_id')->dropDownList(ArrayHelper::getColumn($folders, 'name'))
            : '';
    }

    public function angleField(): ActiveField|string
    {
        if ($this->model->isTransformableImage()) {
            if ($options = $this->getAngleOptions()) {
                return $this->field($this->model, 'angle')->dropDownList($options, ['prompt' => '']);
            }
        }

        return '';
    }

    public function altTextField(?array $options = []): ActiveField|string
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

    public function imageFields(): ActiveField|string
    {
        $fields = [];

        if ($ratios = $this->getRatioOptions()) {
            $content = Html::dropDownList('', null, $ratios, [
                'data-id' => 'ratio',
                'class' => 'form-control',
            ]);

            $fields[] = $this->labelRow(Yii::t('media', 'Aspect ratio'), $content);
        }

        foreach ($this->imageAttributeNames as $attribute) {
            $fields[] = Html::activeHiddenInput($this->model, $attribute, [
                'data-id' => $attribute,
                'value' => '',
            ]);
        }

        $fields[] = $this->horizontalLine();

        return Div::make()
            ->html(...$fields)
            ->attribute('data-id', 'image-wrap')
            ->attribute('hidden', true)
            ->render();
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

        return $module->cropRatios ?? [
            'NaN' => Yii::t('media', 'Free'),
            1 => Yii::t('media', '1:1'),
            strval(4 / 3) => Yii::t('media', '4:3'),
            strval(16 / 9) => Yii::t('media', '16:9'),
        ];
    }

    public function isTransformableImage(): bool
    {
        return $this->model->isTransformableImage()
            && array_intersect($this->imageAttributeNames, $this->model->safeAttributes());
    }

    public function registerClientScript(): void
    {
        ImageCropAsset::registerModule('#' . $this->getId());
    }
}
