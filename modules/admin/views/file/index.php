<?php
/**
 * Files.
 * @see \davidhirtz\yii2\media\modules\admin\controllers\FileController::actionIndex()
 *
 * @var \davidhirtz\yii2\skeleton\web\View $this
 * @var \yii\data\ActiveDataProvider $provider
 */

use davidhirtz\yii2\media\modules\admin\widgets\grid\FileGridView;
use davidhirtz\yii2\media\modules\admin\widgets\nav\FileToolbar;
use davidhirtz\yii2\media\modules\admin\widgets\nav\Submenu;
use davidhirtz\yii2\skeleton\widgets\bootstrap\Panel;

$this->setTitle(Yii::t('media', 'Files'));
?>

<?= Submenu::widget(); ?>
<?= FileToolbar::widget(); ?>

<?= Panel::widget([
    'content' => FileGridView::widget([
        'dataProvider' => $provider,
    ]),
]); ?>