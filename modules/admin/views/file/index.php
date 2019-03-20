<?php
/**
 * Files.
 * @see \davidhirtz\yii2\media\modules\admin\controllers\FileController::actionIndex()
 *
 * @var \davidhirtz\yii2\skeleton\web\View $this
 * @var \yii\data\ActiveDataProvider $provider
 * @var \davidhirtz\yii2\media\modules\admin\models\forms\FileForm $file
 */

use davidhirtz\yii2\media\modules\admin\widgets\grid\FileGridView;
use davidhirtz\yii2\media\modules\admin\widgets\nav\FileSubmenu;
use davidhirtz\yii2\skeleton\widgets\bootstrap\Panel;

$this->setTitle(Yii::t('media', 'Files'));
$this->setBreadcrumb($this->title, ['index']);
?>

<?= FileSubmenu::widget([
    'file' => $file,
]); ?>

<?= Panel::widget([
    'content' => FileGridView::widget([
        'dataProvider' => $provider,
        'file' => $file,
    ]),
]); ?>