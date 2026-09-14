<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Forms;

use Hirtz\Media\Models\Actions\SaveFolderRedirects;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Widgets\Forms\ActiveForm;
use Hirtz\Skeleton\Widgets\Forms\Fields\InputField;
use Override;
use Stringable;
use Yii;

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
        if (!$this->model->getIsNewRecord() && !static::getModule()->enableRenameFolders) {
            return null;
        }

        return InputField::make()
            ->property('path')
            ->prepend(static::getModule()->baseUrl)
            ->hint($this->getPathHint());
    }

    protected function getPathHint(): ?string
    {
        if ($this->model->getIsNewRecord() || !$this->model->file_count) {
            return null;
        }

        $params = ['count' => $this->model->file_count];

        return SaveFolderRedirects::isWithinLimit($this->model)
            ? Yii::t('media', 'FOLDER_PATH_REDIRECT_HINT', $params)
            : Yii::t('media', 'FOLDER_PATH_NO_REDIRECT_HINT', $params);
    }
}
