<?php
declare(strict_types=1);

/**
 * Files.
 * @see \davidhirtz\yii2\media\modules\admin\controllers\FileController::actionIndex()
 *
 * @var \davidhirtz\yii2\skeleton\web\View $this
 * @var \yii\data\ActiveDataProvider $provider
 */

use davidhirtz\yii2\media\modules\admin\widgets\grids\FileGridView;
use davidhirtz\yii2\media\modules\admin\widgets\navs\Submenu;
use davidhirtz\yii2\skeleton\widgets\bootstrap\Panel;

$this->setTitle(Yii::t('media', 'Files'));
?>

<?= Submenu::widget(); ?>

<?= Panel::widget([
    'content' => FileGridView::widget([
        'dataProvider' => $provider,
    ]),
]); ?>
