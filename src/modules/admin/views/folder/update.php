<?php

declare(strict_types=1);

/**
 * @see FolderController::actionUpdate()
 *
 * @var View $this
 * @var Folder $folder
 */

use Hirtz\Media\models\Folder;
use Hirtz\Media\modules\admin\controllers\FolderController;
use Hirtz\Media\modules\admin\widgets\forms\FolderActiveForm;
use Hirtz\Media\modules\admin\widgets\navs\MediaSubmenu;
use Hirtz\Skeleton\web\View;
use Hirtz\Skeleton\widgets\forms\DeleteActiveForm;
use Hirtz\Skeleton\widgets\forms\FormContainer;

$this->title(Yii::t('media', 'Edit Folder'));

echo MediaSubmenu::make();

echo FormContainer::make()
    ->title($this->title)
    ->form(FolderActiveForm::make()
        ->model($folder));

if ($folder->isDeletable()) {
    echo FormContainer::make()
        ->danger()
        ->title(Yii::t('media', 'Delete Folder'))
        ->form(DeleteActiveForm::make()
            ->model($folder)
            ->property('name')
            ->message(Yii::t('media', 'Please type the folder name in the text field below to delete all related files. This cannot be undone, please be certain!')));
}
