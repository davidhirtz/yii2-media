<?php
/**
 * @see FolderController::actionCreate()
 *
 * @var View $this
 * @var Folder $folder
 */

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\admin\controllers\FolderController;
use davidhirtz\yii2\media\modules\admin\widgets\forms\FolderActiveForm;
use davidhirtz\yii2\media\modules\admin\widgets\navs\Submenu;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\web\View;
use davidhirtz\yii2\skeleton\widgets\bootstrap\Panel;

$this->setTitle(Yii::t('media', 'Create New Folder'));
?>

<?= Submenu::widget(); ?>

<?= Html::errorSummary($folder); ?>

<?= Panel::widget([
    'title' => $this->title,
    'content' => FolderActiveForm::widget([
        'model' => $folder,
    ]),
]); ?>
