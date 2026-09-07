<?php

declare(strict_types=1);

/**
 * @see FileController::actionRelations()
 *
 * @var View $this
 * @var File $file
 */

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Controllers\FileController;
use Hirtz\Media\Modules\Admin\Widgets\Grids\FileRelationGridContainer;
use Hirtz\Media\Modules\Admin\Widgets\Navs\FileHeader;
use Hirtz\Media\Modules\Admin\Widgets\Navs\FileSubmenu;
use Hirtz\Skeleton\Web\View;

echo FileHeader::make()
    ->model($file);

echo FileSubmenu::make()
    ->model($file);

echo FileRelationGridContainer::make()
    ->file($file);
