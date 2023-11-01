<?php
/**
 * Update folder.
 * @see \davidhirtz\yii2\media\modules\admin\controllers\FolderController::actionUpdate()
 *
 * @var \davidhirtz\yii2\skeleton\web\View $this
 * @var \davidhirtz\yii2\media\models\Folder $folder
 */

use davidhirtz\yii2\media\modules\admin\widgets\nav\Submenu;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\widgets\bootstrap\Panel;
use davidhirtz\yii2\skeleton\widgets\forms\DeleteActiveForm;

$this->setTitle(Yii::t('media', 'Edit Folder'));
?>

<?= Submenu::widget(); ?>

<?= Html::errorSummary($folder); ?>

<?= Panel::widget([
    'title' => $this->title,
    'content' => $folder->getActiveForm()::widget([
        'model' => $folder,
    ]),
]); ?>

<?php if ($folder->isDeletable()) {
    echo Panel::widget([
        'type' => 'danger',
        'title' => Yii::t('media', 'Delete Folder'),
        'content' => DeleteActiveForm::widget([
            'model' => $folder,
            'attribute' => 'name',
            'message' => Yii::t('media', 'Please type the folder name in the text field below to delete all related files. This cannot be undone, please be certain!')
        ]),
    ]);
} ?>