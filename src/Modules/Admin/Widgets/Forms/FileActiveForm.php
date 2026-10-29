<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Forms;

use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Module;
use Hirtz\Media\Modules\Admin\Widgets\Forms\Fields\FilePreviewField;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Helpers\ArrayHelper;
use Hirtz\Skeleton\Widgets\Forms\ActiveForm;
use Hirtz\Skeleton\Widgets\Forms\Fields\InputField;
use Hirtz\Skeleton\Widgets\Forms\Fields\SelectField;
use Hirtz\Skeleton\Widgets\Forms\FormText;
use Hirtz\Skeleton\Widgets\Forms\Traits\CustomAttributeFieldsTrait;
use Override;
use Stringable;
use Yii;

/**
 * @property Module $module
 * @property File $model
 */
class FileActiveForm extends ActiveForm
{
    use CustomAttributeFieldsTrait;
    use ModuleTrait;

    protected bool $hasStickyButtons = true;
    /**
     * @var list<string>
     */
    protected array $imageAttributeNames = ['width', 'height', 'x', 'y'];

    #[Override]
    protected function configure(): void
    {
        $this->attributes['data-id'] = 'file-form';

        parent::configure();
    }

    #[Override]
    protected function getDefaultRows(): array
    {
        $plainText = $this->getPlainTextCustomAttributeNames();
        $editable = array_values(array_diff(array_keys($this->model->getCustomAttributeDefinitions()), $plainText));

        return [
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
            ],
            [
                $this->getAltTextField(),
                ...$this->getCustomAttributeFields(except: $plainText),
            ],
            [
                $this->getAngleField(),
                $this->getDimensionsField(),
                $this->getSizeField(),
                ...$this->getCustomAttributeFields(except: $editable),
                $this->getWidthField(),
                $this->getHeightField(),
                $this->getXField(),
                $this->getYField(),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    protected function getPlainTextCustomAttributeNames(): array
    {
        $names = [];

        foreach ($this->model->getCustomAttributeDefinitions() as $definition) {
            if ($definition->isPlainText($this->model)) {
                $names[] = $definition->name;
            }
        }

        return $names;
    }

    protected function getPreview(): ?Stringable
    {
        return FilePreviewField::make()
            ->file($this->model);
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
        $directory = $this->model->getHashDirectory();

        $field = InputField::make()
            ->property('basename')
            ->append(".{$this->model->extension}");

        return $directory === null ? $field : $field
            ->prepend("$directory/")
            ->attribute('value', substr((string)$this->model->basename, strlen($directory) + 1));
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

    /**
     * @return array<int, string>
     */
    protected function getAngleOptions(): array
    {
        return [
            180 => Yii::t('media', 'FILE_ANGLE_180_NAME'),
            90 => Yii::t('media', 'FILE_ANGLE_90_NAME'),
            -90 => Yii::t('media', 'FILE_ANGLE_MINUS_90_NAME'),
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
                ->label(Yii::t('media', 'FILE_ASPECT_RATIO_LABEL'))
                ->items($items)
                ->prompt()
            : null;
    }

    /**
     * @return array<string, string>
     */
    protected function getRatioItems(): array
    {
        return $this->module->cropRatios ?? [
            'NaN' => Yii::t('media', 'FILE_ASPECT_RATIO_FREE_NAME'),
            1 => Yii::t('media', 'FILE_ASPECT_RATIO_1_1_NAME'),
            strval(4 / 3) => Yii::t('media', 'FILE_ASPECT_RATIO_4_3_NAME'),
            strval(16 / 9) => Yii::t('media', 'FILE_ASPECT_RATIO_16_9_NAME'),
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
}
