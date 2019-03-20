<?php
/**
 * Update file.
 * @see \davidhirtz\yii2\media\modules\admin\controllers\FileController::actionUpdate()
 *
 * @var \davidhirtz\yii2\skeleton\web\View $this
 * @var \davidhirtz\yii2\media\modules\admin\models\forms\FileForm $file
 */

$this->setTitle(Yii::t('media', 'Edit File'));
$this->setBreadcrumb(Yii::t('media', 'Files'), ['index']);

use davidhirtz\yii2\media\modules\admin\widgets\forms\FileActiveForm;
use davidhirtz\yii2\media\modules\admin\widgets\nav\FileSubmenu;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\widgets\bootstrap\Panel;
use davidhirtz\yii2\skeleton\widgets\forms\DeleteActiveForm; ?>

<?= Html::errorSummary($file); ?>

<?= FileSubmenu::widget([
	'file' => $file,
]); ?>

<?= Panel::widget([
	'title'=>$this->title,
	'content'=>FileActiveForm::widget([
		'model'=>$file,
	]),
]); ?>

<?= Panel::widget([
	'type'=>'danger',
	'title'=>Yii::t('media', 'Delete File'),
	'content'=>DeleteActiveForm::widget([
		'model'=>$file,
	]),
]); ?>