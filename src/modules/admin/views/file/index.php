<?php

declare(strict_types=1);

/**
 * @see \davidhirtz\yii2\media\modules\admin\controllers\FileController::actionIndex()
 *
 * @var View $this
 * @var ActiveDataProvider $provider
 */

use davidhirtz\yii2\media\modules\admin\widgets\grids\FileGridView;
use davidhirtz\yii2\media\modules\admin\widgets\navs\MediaSubmenu;
use davidhirtz\yii2\skeleton\web\View;
use davidhirtz\yii2\skeleton\widgets\grids\GridContainer;
use yii\data\ActiveDataProvider;

$this->title(Yii::t('media', 'Files'));

echo MediaSubmenu::make();

echo GridContainer::make()
    ->grid(FileGridView::make()
        ->provider($provider));
