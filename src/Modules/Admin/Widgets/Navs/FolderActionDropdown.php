<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\Admin\Widgets\Buttons\FolderCreateButton;
use Hirtz\Media\Modules\Admin\Widgets\Buttons\FolderDeleteButton;
use Hirtz\Skeleton\Widgets\Buttons\Button;
use Hirtz\Skeleton\Widgets\Navs\ActionDropdown;
use Hirtz\Skeleton\Widgets\Traits\ModelTrait;
use Override;
use Stringable;
use Yii;

class FolderActionDropdown extends ActionDropdown
{
    /**
     * @use ModelTrait<Folder>
     */
    use ModelTrait;

    #[Override]
    protected function configure(): void
    {
        $this->addItem(
            $this->getFolderCreateButton(),
            $this->getFileIndexButton(),
            $this->getFolderDeleteButton(),
        );

        parent::configure();
    }

    protected function getFolderCreateButton(): ?Stringable
    {
        return FolderCreateButton::make();
    }

    protected function getFileIndexButton(): ?Stringable
    {
        return Button::make()
            ->primary()
            ->text(Yii::t('media', 'FOLDER_ACTION_DROPDOWN_VIEW_FILES'))
            ->icon('photo-film')
            ->url(['/admin/media/file/index', 'folder' => $this->model->id])
            ->roles([File::AUTH_FILE])
            ->visible($this->model->file_count > 0);
    }

    protected function getFolderDeleteButton(): ?Stringable
    {
        return FolderDeleteButton::make()
            ->model($this->model);
    }
}
