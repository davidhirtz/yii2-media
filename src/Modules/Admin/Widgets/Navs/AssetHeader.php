<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\Asset;
use Hirtz\Skeleton\Widgets\Navs\Header;
use Hirtz\Skeleton\Widgets\Traits\ModelTrait;
use Override;

/**
 * The header of a page scoped to one asset. Its breadcrumbs are built from {@see Asset::$model}, so they lead back
 * to whichever record the asset belongs to without this widget knowing the record's bundle.
 */
class AssetHeader extends Header
{
    /**
     * @use ModelTrait<Asset>
     */
    use ModelTrait;

    #[Override]
    protected function configure(): void
    {
        $this->title ??= $this->model->getAdminName();
        $this->url ??= $this->model->getAdminRoute() ?: null;

        $this->addAssetBreadcrumbs();

        parent::configure();
    }

    protected function addAssetBreadcrumbs(): void
    {
        $model = $this->model->model;

        $this->addBreadcrumb($model->getAdminName(), $model->getAdminRoute() ?: null);
        $this->addBreadcrumb(
            $model->getAttributeLabel('asset_count'),
            $this->model::getAdminIndexRoute($model),
        );
    }
}
