<?php
/**
 * Folders.
 * @see \davidhirtz\yii2\media\modules\admin\controllers\FolderController::actionIndex()
 *
 * @var \davidhirtz\yii2\skeleton\web\View $this
 * @var \yii\data\ActiveDataProvider $provider
 * @var \davidhirtz\yii2\media\models\Folder $folder
 */

use davidhirtz\yii2\media\modules\admin\widgets\grid\FolderGridView;
use davidhirtz\yii2\media\modules\admin\widgets\nav\Submenu;
use davidhirtz\yii2\skeleton\widgets\bootstrap\Panel;

$this->setTitle(Yii::t('media', 'Folders'));
?>

<?= Submenu::widget(); ?>

<?= Panel::widget([
    'content' => FolderGridView::widget([
        'dataProvider' => $provider,
        'folder' => $folder,
    ]),
]); ?>