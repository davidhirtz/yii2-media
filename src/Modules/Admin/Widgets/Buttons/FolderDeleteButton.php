<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Buttons;

use Hirtz\Skeleton\I18n\Lang;
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
            && Yii::$app->getUser()->can(Folder::AUTH_FOLDER_DELETE, ['folder' => $this->model]);
    }

    #[Override]
    protected function configure(): void
    {
        $this->label ??= Lang::t('media', 'FOLDER_DELETE_DELETE_FOLDER');
        $this->message ??= Lang::t('media', 'FOLDER_DELETE_TYPE_FOLDER');
        $this->property ??= 'name';

        parent::configure();
    }
}
