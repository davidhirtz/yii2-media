<?php
/**
 * @see FileController::actionUpdate()
 *
 * @var View $this
 * @var File $file
 */

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\controllers\FileController;
use davidhirtz\yii2\media\modules\admin\widgets\forms\FileActiveForm;
use davidhirtz\yii2\media\modules\admin\widgets\grids\TransformationGridView;
use davidhirtz\yii2\media\modules\admin\widgets\navs\Submenu;
use davidhirtz\yii2\media\modules\admin\widgets\panels\FileHelpPanel;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\web\View;
use davidhirtz\yii2\skeleton\widgets\bootstrap\Panel;
use davidhirtz\yii2\skeleton\widgets\forms\DeleteActiveForm;

$this->setTitle(Yii::t('media', 'Edit File'));
?>

<?= Submenu::widget([
    'file' => $file,
]); ?>

<?= Html::errorSummary($file); ?>

<?= Panel::widget([
    'title' => $this->title,
    'content' => FileActiveForm::widget([
        'model' => $file,
    ]),
]); ?>

<?= FileHelpPanel::widget([
    'id' => 'operations',
    'model' => $file,
]); ?>

<?php foreach ($file->getActiveRelatedModels() as $relation) {
    echo $relation::instance()->getFilePanelClass()::widget([
        'file' => $file,
    ]);
} ?>

<?php if ($file->transformation_count) {
    echo Panel::widget([
        'title' => Yii::t('media', 'Transformations'),
        'content' => TransformationGridView::widget([
            'file' => $file,
        ]),
    ]);
} ?>

<?= Panel::widget([
    'type' => 'danger',
    'title' => Yii::t('media', 'Delete File'),
    'content' => DeleteActiveForm::widget([
        'model' => $file,
    ]),
]); ?>