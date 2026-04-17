<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Buttons;

use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Html\P;
use Hirtz\Skeleton\Widgets\Buttons\DeleteButton;
use Override;
use Stringable;
use Yii;

/**
 * @property Folder $model
 */
class FolderDeleteButton extends DeleteButton
{
    public function isVisible(): bool
    {
        return parent::isVisible()
            && $this->model->isDeletable()
            && Yii::$app->getUser()->can(Folder::AUTH_FOLDER_DELETE, ['folder' => $this->model]);
    }

    #[Override]
    protected function configure(): void
    {
        $this->property ??= 'name';
        $this->label ??= Yii::t('media', 'Delete Folder');

        $this->addContent($this->getMessage());

        parent::configure();
    }

    protected function getMessage(): ?Stringable
    {
        return P::make()->text(Yii::t('media', 'Please type the folder name in the text field below to delete all related files. This cannot be undone, please be certain!'));
    }
}
