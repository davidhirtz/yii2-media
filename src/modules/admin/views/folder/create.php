<?php

declare(strict_types=1);

/**
 * @see FolderController::actionCreate()
 *
 * @var View $this
 * @var Folder $folder
 */

use Hirtz\Media\models\Folder;
use Hirtz\Media\modules\admin\controllers\FolderController;
use Hirtz\Media\modules\admin\widgets\forms\FolderActiveForm;
use Hirtz\Media\modules\admin\widgets\navs\MediaSubmenu;
use Hirtz\Skeleton\web\View;
use Hirtz\Skeleton\widgets\forms\FormContainer;

$this->title(Yii::t('media', 'Create New Folder'));

echo MediaSubmenu::make();

echo FormContainer::make()
    ->title($this->title)
    ->form(FolderActiveForm::make()
        ->model($folder));
