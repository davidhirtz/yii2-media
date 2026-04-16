<?php

declare(strict_types=1);

/**
 * @see \Hirtz\Media\Modules\Admin\Controllers\FileController::actionIndex()
 *
 * @var View $this
 * @var ActiveDataProvider $provider
 */

use Hirtz\Media\Modules\Admin\Widgets\Grids\FileGridView;
use Hirtz\Media\Modules\Admin\Widgets\Navs\FileHeader;
use Hirtz\Skeleton\Web\View;
use Hirtz\Skeleton\Widgets\Grids\GridContainer;
use yii\data\ActiveDataProvider;

echo FileHeader::make()
    ->provider($provider);

echo GridContainer::make()
    ->grid(FileGridView::make()
        ->provider($provider));
