<?php

declare(strict_types=1);

/**
 * @see \Hirtz\Media\modules\admin\controllers\FolderController::actionIndex()
 *
 * @var View $this
 * @var ActiveDataProvider $provider
 * @var Folder $folder
 */

use Hirtz\Media\models\Folder;
use Hirtz\Media\modules\admin\widgets\grids\FolderGridView;
use Hirtz\Media\modules\admin\widgets\navs\MediaSubmenu;
use Hirtz\Skeleton\web\View;
use Hirtz\Skeleton\widgets\grids\GridContainer;
use yii\data\ActiveDataProvider;

$this->title(Yii::t('media', 'Folders'));

echo MediaSubmenu::make();

echo GridContainer::make()
    ->grid(FolderGridView::make()
    ->provider($provider));
