<?php
/**
 * Create folder.
 * @see \davidhirtz\yii2\media\modules\admin\controllers\FolderController::actionCreate()
 *
 * @var \davidhirtz\yii2\skeleton\web\View $this
 * @var \davidhirtz\yii2\media\modules\admin\models\forms\FolderForm $folder
 */


use davidhirtz\yii2\media\modules\admin\widgets\forms\FolderActiveForm;
use davidhirtz\yii2\media\modules\admin\widgets\nav\Submenu;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\widgets\bootstrap\Panel;

$this->setTitle(Yii::t('media', 'Create New Folder'));
?>

<?= Submenu::widget(); ?>
<?= Html::errorSummary($folder); ?>

<?= Panel::widget([
	'title'=>$this->title,
	'content'=>FolderActiveForm::widget([
		'model'=>$folder,
	]),
]); ?>
