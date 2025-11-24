<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\forms;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\widgets\forms\ActiveForm;
use davidhirtz\yii2\skeleton\widgets\forms\fields\InputField;
use Override;
use Stringable;

/**
 * @property Folder $model
 */
class FolderActiveForm extends ActiveForm
{
    use ModuleTrait;

    #[Override]
    public function configure(): void
    {
        $this->rows ??= [
            $this->getNameField(),
            $this->getPathField(),
        ];

        parent::configure();
    }

    protected function getNameField(): Stringable
    {
        return InputField::make()
            ->property('name');
    }

    protected function getPathField(): ?Stringable
    {
        return $this->model->getIsNewRecord() || static::getModule()->enableRenameFolders
            ? InputField::make()
                ->property('path')
                ->prepend(static::getModule()->baseUrl)
            : null;
    }
}
