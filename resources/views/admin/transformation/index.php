<?php

declare(strict_types=1);

/**
 * @see TransformationController::actionIndex()
 *
 * @var View $this
 * @var File $file
 */

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Controllers\TransformationController;
use Hirtz\Media\Modules\Admin\Widgets\Grids\TransformationGridView;
use Hirtz\Media\Modules\Admin\Widgets\Navs\FileHeader;
use Hirtz\Media\Modules\Admin\Widgets\Navs\FileSubmenu;
use Hirtz\Skeleton\I18n\Lang;
use Hirtz\Skeleton\Web\View;
use Hirtz\Skeleton\Widgets\Grids\GridContainer;

echo FileHeader::make()
    ->model($file);

echo FileSubmenu::make()
    ->model($file);

echo GridContainer::make()
    ->grid(TransformationGridView::make()
        ->file($file));
