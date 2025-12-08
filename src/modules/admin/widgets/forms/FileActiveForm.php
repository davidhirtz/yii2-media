<?php

declare(strict_types=1);

namespace Hirtz\Media\modules\admin\widgets\forms;

use Hirtz\Media\assets\ImageCropAssetBundle;
use Hirtz\Media\models\collections\FolderCollection;
use Hirtz\Media\models\File;
use Hirtz\Media\modules\admin\Module;
use Hirtz\Media\modules\admin\widgets\forms\fields\FilePreview;
use Hirtz\Media\modules\ModuleTrait;
use Hirtz\Skeleton\helpers\ArrayHelper;
use Hirtz\Skeleton\html\Button;
use Hirtz\Skeleton\widgets\forms\ActiveForm;
use Hirtz\Skeleton\widgets\forms\FormText;
use Hirtz\Skeleton\widgets\forms\fields\InputField;
use Hirtz\Skeleton\widgets\forms\fields\SelectField;
use Hirtz\Skeleton\widgets\forms\FormRow;
use Override;
use Stringable;
use Yii;

/**
 * @property File $model
 */
class FileActiveForm extends ActiveForm
{
    use ModuleTrait;

    public bool $hasStickyButtons = true;
    protected array $imageAttributeNames = ['width', 'height', 'x', 'y'];

    #[Override]
    protected function configure(): void
    {
        $this->attributes['data-id'] = 'file-form';

        $this->rows ??= [
            [
                $this->getPreview(),
            ],
            [
                $this->getRatioField(),
            ],
            [
                $this->getFolderIdField(),
                $this->getNameField(),
                $this->getBasenameField(),
                $this->getAltTextField(),
                $this->getAngleField(),
            ],
            [
                $this->getDimensionsField(),
                $this->getSizeField(),
                $this->getWidthField(),
                $this->getHeightField(),
                $this->getXField(),
                $this->getYField(),
            ],
        ];

        $this->submitButtonText = Yii::t('skeleton', 'Update');

        if ($this->isTransformableImage()) {
            $this->buttons = [
                $this->getSubmitButton(),
                Button::make()
                    ->secondary()
                    ->icon('image')
                    ->text(Yii::t('media', 'Edit Image'))
                    ->attribute('data-id', 'image-open'),
                Button::make()
                    ->secondary()
                    ->text(Yii::t('media', 'Cancel'))
                    ->attribute('data-id', 'image-cancel')
                    ->attribute('hidden', true),
            ];

            $this->registerClientScript();
        }

        parent::configure();
    }

    /**
     * This method uses old attributes for basename and sizes as they would only differ on an error in which case the
     * new attributes might not be accurate.
     */
    protected function getPreview(): ?Stringable
    {
        if (!$this->model->hasPreview()) {
            return null;
        }

        $file = clone $this->model;
        $file->setAttributes($this->model->getOldAttributes(), false);

        return FormRow::make()
            ->content(FilePreview::make()
                ->attribute('data-id', 'image')
                ->model($file));
    }

    protected function getFolderIdField(): ?Stringable
    {
        $folders = ArrayHelper::getColumn(FolderCollection::getAll(), 'name');

        return count($folders) > 1
            ? SelectField::make()
                ->property('folder_id')
                ->items($folders)
            : null;
    }

    protected function getNameField(): ?Stringable
    {
        return InputField::make()
            ->property('name');
    }

    protected function getBasenameField(): ?Stringable
    {
        return InputField::make()
            ->property('basename')
            ->append(".{$this->model->extension}");
    }

    protected function getAngleField(): ?Stringable
    {
        if (!$this->isTransformableImage()) {
            return null;
        }

        $options = $this->getAngleOptions();

        return count($options) > 1
            ? SelectField::make()
                ->property('angle')
                ->items($options)
                ->prompt()
            : null;
    }

    protected function getAngleOptions(): array
    {
        return [
            180 => Yii::t('media', '180°'),
            90 => Yii::t('media', '90° Clockwise'),
            -90 => Yii::t('media', '90° Counter Clockwise'),
        ];
    }

    protected function getRatioField(): ?Stringable
    {
        if (!$this->isTransformableImage()) {
            return null;
        }

        $items = $this->getRatioItems();

        return count($items) > 1
            ? SelectField::make()
                ->attribute('data-id', 'ratio')
                ->rowAttributes(['hidden' => true])
                ->label(Yii::t('media', 'Aspect ratio'))
                ->items($items)
                ->prompt()
            : null;
    }

    protected function getRatioItems(): array|false
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

    protected function getAltTextField(): ?Stringable
    {
        if (!$this->model->hasPreview()) {
            return null;
        }

        return InputField::make()
            ->property('alt_text');
    }

    protected function getDimensionsField(): ?Stringable
    {
        return $this->model->hasDimensions()
            ? FormText::make()
                ->model($this->model)
                ->property('dimensions')
            : null;
    }

    protected function getSizeField(): ?Stringable
    {
        return $this->model->size
            ? FormText::make()
                ->model($this->model)
                ->property('size')
                ->format('shortSize')
            : null;
    }

    protected function getWidthField(): ?Stringable
    {
        return $this->getHiddenImageFieldByProperty('width');
    }

    protected function getHeightField(): ?Stringable
    {
        return $this->getHiddenImageFieldByProperty('height');
    }

    protected function getXField(): ?Stringable
    {
        return $this->getHiddenImageFieldByProperty('x');
    }

    protected function getYField(): ?Stringable
    {
        return $this->getHiddenImageFieldByProperty('y');
    }

    protected function getHiddenImageFieldByProperty(string $property): ?Stringable
    {
        return $this->isTransformableImage()
            ? InputField::make()
                ->property($property)
                ->attribute('data-id', $property)
                ->type('hidden')
            : null;
    }

    protected function isTransformableImage(): bool
    {
        return $this->model->isTransformableImage()
            && array_intersect($this->imageAttributeNames, $this->model->safeAttributes());
    }

    protected function registerClientScript(): void
    {
        $this->view->registerAssetBundle(ImageCropAssetBundle::class);
    }
}
