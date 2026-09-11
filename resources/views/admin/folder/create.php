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
use Hirtz\Media\Modules\Admin\Widgets\Navs\FolderHeader;
use Hirtz\Skeleton\Web\View;
use Hirtz\Skeleton\Widgets\Forms\FormContainer;

echo FolderHeader::make()
    ->title(Yii::t('media', 'FOLDER_CREATE_TITLE'));

echo FormContainer::make()
    ->form(FolderActiveForm::make()
        ->model($folder));
