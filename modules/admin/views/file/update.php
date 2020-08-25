<?php
/**
 * Update file.
 * @see \davidhirtz\yii2\media\modules\admin\controllers\FileController::actionUpdate()
 *
 * @var \davidhirtz\yii2\skeleton\web\View $this
 * @var \davidhirtz\yii2\media\models\File $file
 */

use davidhirtz\yii2\media\modules\admin\widgets\grid\TransformationGridView;
use davidhirtz\yii2\media\modules\admin\widgets\nav\FileToolbar;
use davidhirtz\yii2\media\modules\admin\widgets\nav\Submenu;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\widgets\bootstrap\Panel;
use davidhirtz\yii2\skeleton\widgets\forms\DeleteActiveForm;
use davidhirtz\yii2\media\modules\admin\widgets\panels\FileHelpPanel;

$this->setTitle(Yii::t('media', 'Edit File'));
?>

<?= Submenu::widget([
    'file' => $file,
]); ?>

<?= FileToolbar::widget([
    'model' => $file,
]); ?>

<?= Html::errorSummary($file); ?>

<?= Panel::widget([
    'title' => $this->title,
    'content' => $file->getActiveForm()::widget([
        'model' => $file,
    ]),
]); ?>

<?= FileHelpPanel::widget([
    'id' => 'operations',
    'model' => $file,
]); ?>

<?php
foreach ($file->getAssetModels() as $asset) {
    echo Panel::widget([
        'id' => 'assets',
        'title' => $asset->getParentName(),
        'content' => $asset->getParentGridView()::widget([
            'file' => $file,
        ]),
    ]);
}
?>

<?php
if ($file->transformation_count) {
    echo Panel::widget([
        'title' => Yii::t('media', 'Transformations'),
        'content' => TransformationGridView::widget([
            'file' => $file,
        ]),
    ]);
}
?>

<?= Panel::widget([
    'type' => 'danger',
    'title' => Yii::t('media', 'Delete File'),
    'content' => DeleteActiveForm::widget([
        'model' => $file,
    ]),
]); ?>