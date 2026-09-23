<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Widgets\Navs\NavItem;
use Hirtz\Skeleton\Widgets\Navs\Submenu;
use Hirtz\Skeleton\Widgets\Traits\ModelTrait;
use Override;
use Yii;

class FileSubmenu extends Submenu
{
    /**
     * @use ModelTrait<File>
     */
    use ModelTrait;

    #[Override]
    protected function configure(): void
    {
        $this->addItem(
            file: $this->getFileUpdateItem(),
            assets: $this->getAssetsItem(),
            transformations: $this->getTransformationsItem(),
        );

        parent::configure();
    }

    protected function getFileUpdateItem(): ?NavItem
    {
        return NavItem::make()
            ->icon('cog')
            ->label($this->model->getAdminType())
            ->addRoute('admin/media/file/update')
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
            ->label(Yii::t('media', 'COMMON_TRANSFORMATIONS'))
            ->addRoute('admin/media/transformation/index')
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
            ->label(Yii::t('media', 'COMMON_ASSETS'))
            ->addRoute('admin/media/asset')
            ->url(['/admin/media/asset/index', 'file' => $this->model->id]);
    }
}
