<?php

declare(strict_types=1);

/**
 * @see FileReferenceController::actionIndex()
 *
 * @var View $this
 * @var File $file
 */

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Controllers\FileReferenceController;
use Hirtz\Media\Modules\Admin\Widgets\Grids\FileReferenceGridView;
use Hirtz\Media\Modules\Admin\Widgets\Navs\FileHeader;
use Hirtz\Media\Modules\Admin\Widgets\Navs\FileSubmenu;
use Hirtz\Skeleton\Web\View;
use Hirtz\Skeleton\Widgets\Grids\GridContainer;

echo FileHeader::make()
    ->model($file);

echo FileSubmenu::make()
    ->model($file);

echo GridContainer::make()
    ->grid(FileReferenceGridView::make()
        ->file($file));
