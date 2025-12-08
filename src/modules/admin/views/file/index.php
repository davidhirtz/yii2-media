<?php

declare(strict_types=1);

/**
 * @see \Hirtz\Media\modules\admin\controllers\FileController::actionIndex()
 *
 * @var View $this
 * @var ActiveDataProvider $provider
 */

use Hirtz\Media\modules\admin\widgets\grids\FileGridView;
use Hirtz\Media\modules\admin\widgets\navs\MediaSubmenu;
use Hirtz\Skeleton\web\View;
use Hirtz\Skeleton\widgets\grids\GridContainer;
use yii\data\ActiveDataProvider;

$this->title(Yii::t('media', 'Files'));

echo MediaSubmenu::make();

echo GridContainer::make()
    ->grid(FileGridView::make()
        ->provider($provider));
