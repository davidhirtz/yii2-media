<?php

declare(strict_types=1);

/**
 * @see \davidhirtz\yii2\media\modules\admin\controllers\FolderController::actionIndex()
 *
 * @var View $this
 * @var ActiveDataProvider $provider
 * @var Folder $folder
 */

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\admin\widgets\grids\FolderGridView;
use davidhirtz\yii2\media\modules\admin\widgets\navs\MediaSubmenu;
use davidhirtz\yii2\skeleton\web\View;
use davidhirtz\yii2\skeleton\widgets\grids\GridContainer;
use yii\data\ActiveDataProvider;

$this->title(Yii::t('media', 'Folders'));

echo MediaSubmenu::make();

echo GridContainer::make()
    ->grid(FolderGridView::make()
    ->provider($provider));
