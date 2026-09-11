<?php

declare(strict_types=1);

/**
 * @see AbstractAssetController::actionIndex()
 *
 * @var View $this
 * @var AssetModelInterface $model
 * @var AssetArrayDataProvider $provider
 */

use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Media\Modules\Admin\Controllers\AbstractAssetController;
use Hirtz\Media\Modules\Admin\Data\AssetArrayDataProvider;
use Hirtz\Media\Modules\Admin\Widgets\Grids\AssetGridView;
use Hirtz\Media\Modules\Admin\Widgets\Navs\AssetModelActionDropdown;
use Hirtz\Media\Modules\Admin\Widgets\Navs\AssetModelHeader;
use Hirtz\Skeleton\Web\View;
use Hirtz\Skeleton\Widgets\Grids\GridContainer;

echo AssetModelHeader::make()
    ->model($model)
    ->content(AssetModelActionDropdown::make()
        ->provider($provider));

echo GridContainer::make()
    ->grid(AssetGridView::make()
        ->provider($provider));
