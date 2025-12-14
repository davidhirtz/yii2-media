<?php

declare(strict_types=1);

/**
 * @see \Hirtz\Media\Modules\Admin\Controllers\FileController::actionIndex()
 *
 * @var View $this
 * @var ActiveDataProvider $provider
 */

use Hirtz\Media\Modules\Admin\Widgets\Grids\FileGridView;
use Hirtz\Media\Modules\Admin\Widgets\Navs\MediaSubmenu;
use Hirtz\Skeleton\Web\View;
use Hirtz\Skeleton\Widgets\Grids\GridContainer;
use yii\data\ActiveDataProvider;

$this->title(Yii::t('media', 'Files'));

echo MediaSubmenu::make();

echo GridContainer::make()
    ->grid(FileGridView::make()
        ->provider($provider));
