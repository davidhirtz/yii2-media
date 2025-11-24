<?php

declare(strict_types=1);

/**
 * @see FolderController::actionCreate()
 *
 * @var View $this
 * @var Folder $folder
 */

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\admin\controllers\FolderController;
use davidhirtz\yii2\media\modules\admin\widgets\forms\FolderActiveForm;
use davidhirtz\yii2\media\modules\admin\widgets\navs\MediaSubmenu;
use davidhirtz\yii2\skeleton\web\View;
use davidhirtz\yii2\skeleton\widgets\forms\FormContainer;

$this->title(Yii::t('media', 'Create New Folder'));

echo MediaSubmenu::make();

echo FormContainer::make()
    ->title($this->title)
    ->form(FolderActiveForm::make()
        ->model($folder));
