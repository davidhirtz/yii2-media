<?php

declare(strict_types=1);

/**
 * @see FolderController::actionCreate()
 *
 * @var View $this
 * @var Folder $folder
 */

use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\Admin\Controllers\FolderController;
use Hirtz\Media\Modules\Admin\Widgets\Forms\FolderActiveForm;
use Hirtz\Media\Modules\Admin\Widgets\Navs\MediaSubmenu;
use Hirtz\Skeleton\Web\View;
use Hirtz\Skeleton\Widgets\Forms\FormContainer;

$this->title(Yii::t('media', 'Create New Folder'));

echo MediaSubmenu::make();

echo FormContainer::make()
    ->title($this->title)
    ->form(FolderActiveForm::make()
        ->model($folder));
