<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\Admin\Widgets\Buttons\FolderCreateButton;
use Hirtz\Skeleton\I18n\Lang;
use Hirtz\Skeleton\Widgets\Navs\Header;
use Hirtz\Skeleton\Widgets\Traits\ModelTrait;
use Hirtz\Skeleton\Widgets\Traits\ProviderTrait;
use Override;
use Stringable;

class FolderHeader extends Header
{
    /**
     * @use ModelTrait<Folder|null>
     */
    use ModelTrait;
    use ProviderTrait;

    #[Override]
    protected function configure(): void
    {
        if ($this->model) {
            $this->title ??= $this->model->getOldAttribute('name') ?? Lang::t('media', 'COMMON_FOLDER');
            $this->addContent($this->getFolderActionDropdown());
        }

        if ($this->provider) {
            $this->title ??= Lang::t('media', 'COMMON_FOLDERS');
            $this->url ??= ['/admin/media/folder/index'];
            $this->addContent($this->getFolderCreateButton());
        }

        $this->setBreadcrumbs();

        parent::configure();
    }

    protected function setBreadcrumbs(): void
    {
        if (!$this->breadcrumbs) {
            $this->addBreadcrumb(Lang::t('media', 'COMMON_FILES'), ['/admin/media/file/index']);

            if (!$this->provider) {
                $this->addBreadcrumb(Lang::t('media', 'COMMON_FOLDERS'), ['/admin/media/folder/index']);
            }
        }
    }

    protected function getFolderActionDropdown(): ?Stringable
    {
        return FolderActionDropdown::make()
            ->model($this->model);
    }

    protected function getFolderCreateButton(): ?Stringable
    {
        return FolderCreateButton::make();
    }
}
