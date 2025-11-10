<?php

declare(strict_types=1);

/**
 * @see \davidhirtz\yii2\media\modules\admin\controllers\FileController::actionIndex()
 *
 * @var View $this
 * @var ActiveDataProvider $provider
 */

use davidhirtz\yii2\media\modules\admin\widgets\grids\FileGridView;
use davidhirtz\yii2\media\modules\admin\widgets\navs\Submenu;
use davidhirtz\yii2\skeleton\web\View;
use davidhirtz\yii2\skeleton\widgets\bootstrap\Panel;
use yii\data\ActiveDataProvider;

$this->setTitle(Yii::t('media', 'Files'));

echo Submenu::widget();

echo Panel::widget([
    'content' => FileGridView::widget([
        'dataProvider' => $provider,
    ]),
]);
