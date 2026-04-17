<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\Admin\Widgets\Buttons\FolderCreateButton;
use Hirtz\Media\Modules\Admin\Widgets\Buttons\FolderDeleteButton;
use Hirtz\Skeleton\Widgets\Navs\ActionDropdown;
use Hirtz\Skeleton\Widgets\Traits\ModelTrait;
use Override;
use Stringable;

class FolderActionDropdown extends ActionDropdown
{
    /**
     * @use ModelTrait<Folder>
     */
    use ModelTrait;

    #[Override]
    protected function configure(): void
    {
        $this->addItem($this->getFolderCreateButton(), $this->getFolderDeleteButton());
        parent::configure();
    }

    protected function getFolderCreateButton(): ?Stringable
    {
        return FolderCreateButton::make();
    }

    protected function getFolderDeleteButton(): ?Stringable
    {
        return FolderDeleteButton::make()
            ->model($this->model);
    }
}
