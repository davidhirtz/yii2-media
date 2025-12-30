<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Forms\Traits;

use Hirtz\Media\Models\Interfaces\AssetInterface;
use Hirtz\Media\Modules\Admin\Widgets\Forms\Fields\FilePreview;
use Hirtz\Skeleton\Widgets\Forms\Fields\InputField;
use Hirtz\Skeleton\Widgets\Forms\FormRow;
use Stringable;
use yii\db\ActiveRecord;

/**
 * @property ActiveRecord&AssetInterface $model
 */
trait AssetFieldsTrait
{
    protected function getPreview(): ?Stringable
    {
        if (!$this->model->file->hasPreview()) {
            return null;
        }

        return FormRow::make()
            ->content(FilePreview::make()
                ->model($this->model->file));
    }

    public function getAltTextField(): ?Stringable
    {
        return InputField::make()
            ->property('alt_text')
            ->prepare(fn (InputField $field) => $field->placeholder($this->model->file->getI18nAttribute('alt_text')));
    }
}
