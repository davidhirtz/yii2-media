<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\forms\traits;

use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use davidhirtz\yii2\media\modules\admin\widgets\forms\fields\FilePreview;
use davidhirtz\yii2\skeleton\widgets\forms\fields\InputField;
use davidhirtz\yii2\skeleton\widgets\forms\FormRow;
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
