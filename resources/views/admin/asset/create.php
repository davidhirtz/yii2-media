<?php

declare(strict_types=1);

/**
 * @see AbstractAssetController::actionCreate()
 *
 * @var View $this
 * @var AssetModelInterface $model
 * @var FileActiveDataProvider $provider
 */

use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Media\Modules\Admin\Controllers\AbstractAssetController;
use Hirtz\Media\Modules\Admin\Data\FileActiveDataProvider;
use Hirtz\Media\Modules\Admin\Widgets\Grids\FileGridView;
use Hirtz\Media\Modules\Admin\Widgets\Navs\AssetModelHeader;
use Hirtz\Skeleton\Web\View;
use Hirtz\Skeleton\Widgets\Grids\GridContainer;

echo AssetModelHeader::make()
    ->model($model);

echo GridContainer::make()
    ->grid(FileGridView::make()
        ->provider($provider)
        ->model($model));
