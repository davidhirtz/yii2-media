<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Buttons;

use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Widgets\Buttons\DeleteButton;
use Override;
use Yii;

/**
 * @extends DeleteButton<Folder>
 */
class FolderDeleteButton extends DeleteButton
{
    #[Override]
    public function isVisible(): bool
    {
        return parent::isVisible()
            && $this->model->isDeletable()
            && $this->webuser->can(Folder::AUTH_FOLDER_DELETE, ['folder' => $this->model]);
    }

    #[Override]
    protected function configure(): void
    {
        $this->label ??= Yii::t('media', 'FOLDER_DELETE_DELETE_FOLDER');
        $this->message ??= Yii::t('media', 'FOLDER_DELETE_TYPE_FOLDER');
        $this->property ??= 'name';

        parent::configure();
    }
}
