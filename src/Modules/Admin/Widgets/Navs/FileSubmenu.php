<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\File;
use Hirtz\Skeleton\I18n\Lang;
use Hirtz\Skeleton\Widgets\Navs\NavItem;
use Hirtz\Skeleton\Widgets\Navs\Submenu;
use Hirtz\Skeleton\Widgets\Traits\ModelTrait;
use Override;

class FileSubmenu extends Submenu
{
    /**
     * @use ModelTrait<File>
     */
    use ModelTrait;

    protected array $additionalActiveRoutes = [];

    #[Override]
    protected function configure(): void
    {
        $this->addItem(
            $this->getFileUpdateItem(),
            $this->getTransformationsItem(),
            $this->getAssetsItem(),
        );

        parent::configure();
    }

    protected function getFileUpdateItem(): ?NavItem
    {
        return NavItem::make()
            ->icon('cog')
            ->label(Lang::t('skeleton', 'COMMON_GENERAL'))
            ->routes(['admin/media/file/update', ...$this->additionalActiveRoutes['file'] ?? []])
            ->url(['/admin/media/file/update', 'id' => $this->model->id]);
    }

    protected function getTransformationsItem(): ?NavItem
    {
        if (!$this->model->transformation_count) {
            return null;
        }

        return NavItem::make()
            ->badge($this->model->transformation_count)
            ->icon('image')
            ->label(Lang::t('media', 'COMMON_TRANSFORMATIONS'))
            ->routes(['admin/media/transformation/index', ...$this->additionalActiveRoutes['transformations'] ?? []])
            ->url(['/admin/media/transformation/index', 'file' => $this->model->id]);
    }

    protected function getAssetsItem(): ?NavItem
    {
        $count = $this->model->asset_count;

        if (!$count) {
            return null;
        }

        return NavItem::make()
            ->badge($count)
            ->icon('link')
            ->label(Lang::t('media', 'COMMON_ASSETS'))
            ->routes(['admin/media/file-asset', ...$this->additionalActiveRoutes['assets'] ?? []])
            ->url(['/admin/media/file-asset/index', 'file' => $this->model->id]);
    }
}
