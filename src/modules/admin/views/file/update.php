<?php

declare(strict_types=1);

/**
 * @see FileController::actionUpdate()
 *
 * @var View $this
 * @var File $file
 */

use Hirtz\Media\models\File;
use Hirtz\Media\modules\admin\controllers\FileController;
use Hirtz\Media\modules\admin\widgets\forms\FileActiveForm;
use Hirtz\Media\modules\admin\widgets\grids\TransformationGridView;
use Hirtz\Media\modules\admin\widgets\navs\MediaSubmenu;
use Hirtz\Media\modules\admin\widgets\panels\FilePanel;
use Hirtz\Skeleton\web\View;
use Hirtz\Skeleton\widgets\forms\DeleteActiveForm;
use Hirtz\Skeleton\widgets\forms\FormContainer;
use Hirtz\Skeleton\widgets\grids\GridContainer;

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
