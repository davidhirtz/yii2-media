<?php
/**
 * Create file.
 * @see \davidhirtz\yii2\media\modules\admin\controllers\FileController::actionCreate()
 *
 * @var \davidhirtz\yii2\skeleton\web\View $this
 * @var \davidhirtz\yii2\media\modules\admin\models\forms\FileForm $file
 */

$this->setTitle(Yii::t('media', 'Create New File'));
$this->setBreadcrumb(Yii::t('media', 'Files'), ['index']);

use davidhirtz\yii2\media\modules\admin\widgets\forms\FileActiveForm;
use davidhirtz\yii2\media\modules\admin\widgets\nav\FileSubmenu;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\widgets\bootstrap\Panel; ?>

<?= Html::errorSummary($file); ?>

<?= FileSubmenu::widget([
	'title'=>Html::a(Yii::t('media', 'Files'), ['index']),
]); ?>

<?= Panel::widget([
	'title'=>$this->title,
	'content'=>FileActiveForm::widget([
		'model'=>$file,
	]),
]); ?>
