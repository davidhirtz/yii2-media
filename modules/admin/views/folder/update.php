<?php
/**
 * Update folder.
 * @see \davidhirtz\yii2\media\modules\admin\controllers\FolderController::actionUpdate()
 *
 * @var \davidhirtz\yii2\skeleton\web\View $this
 * @var \davidhirtz\yii2\media\modules\admin\models\forms\FolderForm $folder
 */

$this->setTitle(Yii::t('media', 'Edit Folder'));
$this->setBreadcrumb(Yii::t('media', 'Folders'), ['index']);

use davidhirtz\yii2\media\modules\admin\widgets\forms\FolderActiveForm;
use davidhirtz\yii2\media\modules\admin\widgets\nav\Submenu;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\widgets\bootstrap\Panel;
use davidhirtz\yii2\skeleton\widgets\forms\DeleteActiveForm; ?>

<?= Submenu::widget(); ?>

<?= Html::errorSummary($folder); ?>

<?= Panel::widget([
	'title'=>$this->title,
	'content'=>FolderActiveForm::widget([
		'model'=>$folder,
	]),
]); ?>

<?= Panel::widget([
	'type'=>'danger',
	'title'=>Yii::t('media', 'Delete Folder'),
	'content'=>DeleteActiveForm::widget([
		'model'=>$folder,
		'attribute'=>'name',
		'message' => Yii::t('media', 'Please type the folder name in the text field below to delete all related files. This cannot be undone, please be certain!')
	]),
]); ?>