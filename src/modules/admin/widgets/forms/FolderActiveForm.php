<?php

declare(strict_types=1);

namespace Hirtz\Media\modules\admin\widgets\forms;

use Hirtz\Media\models\Folder;
use Hirtz\Media\modules\ModuleTrait;
use Hirtz\Skeleton\widgets\forms\ActiveForm;
use Hirtz\Skeleton\widgets\forms\fields\InputField;
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
