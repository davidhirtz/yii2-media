<?php

declare(strict_types=1);

/**
 * @see \Hirtz\Media\Modules\Admin\Controllers\FileController::actionIndex()
 *
 * @var View $this
 * @var FileActiveDataProvider $provider
 */

use Hirtz\Media\Modules\Admin\Data\FileActiveDataProvider;
use Hirtz\Media\Modules\Admin\Widgets\Grids\FileGridView;
use Hirtz\Media\Modules\Admin\Widgets\Navs\FileHeader;
use Hirtz\Skeleton\Web\View;
use Hirtz\Skeleton\Widgets\Grids\GridContainer;

echo FileHeader::make()
    ->provider($provider);

echo GridContainer::make()
    ->grid(FileGridView::make()
        ->provider($provider));
