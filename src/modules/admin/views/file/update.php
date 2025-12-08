<?php

declare(strict_types=1);

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
use davidhirtz\yii2\media\modules\admin\widgets\navs\MediaSubmenu;
use davidhirtz\yii2\media\modules\admin\widgets\panels\FilePanel;
use davidhirtz\yii2\skeleton\web\View;
use davidhirtz\yii2\skeleton\widgets\forms\DeleteActiveForm;
use davidhirtz\yii2\skeleton\widgets\forms\FormContainer;
use davidhirtz\yii2\skeleton\widgets\grids\GridContainer;

$this->title(Yii::t('media', 'Edit File'));

echo MediaSubmenu::make()
    ->file($file);

echo FormContainer::make()
    ->form(FileActiveForm::make()
        ->model($file));

echo FilePanel::make()
    ->model($file);

// Todo
//foreach ($file->getActiveRelatedModels() as $relation) {
//    echo $relation::instance()->getFilePanelClass()::widget([
//        'file' => $file,
//    ]);
//}

if ($file->transformation_count) {
    echo GridContainer::make()
        ->title(Yii::t('media', 'Transformations'))
        ->grid(TransformationGridView::make()
            ->file($file));
}

if (Yii::$app->getUser()->can(File::AUTH_FILE_DELETE, ['file' => $file])) {
    echo FormContainer::make()
        ->danger()
        ->title(Yii::t('media', 'Delete File'))
        ->form(DeleteActiveForm::make()
            ->model($file));
}
