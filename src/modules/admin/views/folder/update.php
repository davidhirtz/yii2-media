<?php

declare(strict_types=1);

/**
 * @see FolderController::actionUpdate()
 *
 * @var View $this
 * @var Folder $folder
 */

use Hirtz\Media\models\Folder;
use Hirtz\Media\Modules\Admin\Controllers\FolderController;
use Hirtz\Media\Modules\Admin\Widgets\Forms\FolderActiveForm;
use Hirtz\Media\Modules\Admin\Widgets\Navs\MediaSubmenu;
use Hirtz\Skeleton\Web\View;
use Hirtz\Skeleton\Widgets\Forms\DeleteActiveForm;
use Hirtz\Skeleton\Widgets\Forms\FormContainer;

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
