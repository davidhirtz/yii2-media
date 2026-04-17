<?php

declare(strict_types=1);

/**
 * @see \Hirtz\Media\Modules\Admin\Controllers\FolderController::actionIndex()
 *
 * @var View $this
 * @var ActiveDataProvider $provider
 * @var Folder $folder
 */

use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\Admin\Widgets\Grids\FolderGridView;
use Hirtz\Media\Modules\Admin\Widgets\Navs\FolderHeader;
use Hirtz\Skeleton\Web\View;
use Hirtz\Skeleton\Widgets\Grids\GridContainer;
use yii\data\ActiveDataProvider;

$this->title(Yii::t('media', 'Folders'));

echo FolderHeader::make()
    ->provider($provider);

echo GridContainer::make()
    ->grid(FolderGridView::make()
        ->provider($provider));
