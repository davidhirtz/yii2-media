<?php

declare(strict_types=1);

/**
 * @see FileController::actionUpdate()
 *
 * @var View $this
 * @var File $file
 */

use Hirtz\Media\models\File;
use Hirtz\Media\Modules\Admin\Controllers\FileController;
use Hirtz\Media\Modules\Admin\Widgets\Forms\FileActiveForm;
use Hirtz\Media\Modules\Admin\Widgets\Grids\TransformationGridView;
use Hirtz\Media\Modules\Admin\Widgets\Navs\MediaSubmenu;
use Hirtz\Media\Modules\Admin\Widgets\Panels\FilePanel;
use Hirtz\Skeleton\Web\View;
use Hirtz\Skeleton\Widgets\Forms\DeleteActiveForm;
use Hirtz\Skeleton\Widgets\Forms\FormContainer;
use Hirtz\Skeleton\Widgets\Grids\GridContainer;

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
