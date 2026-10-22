<?php

declare(strict_types=1);

/**
 * @see FileController::actionUpdate()
 *
 * @var View $this
 * @var File $file
 */

use Hirtz\Media\Images\ImageProcessor;
use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Controllers\FileController;
use Hirtz\Media\Modules\Admin\Widgets\Forms\FileActiveForm;
use Hirtz\Media\Modules\Admin\Widgets\Navs\FileActionDropdown;
use Hirtz\Media\Modules\Admin\Widgets\Navs\FileHeader;
use Hirtz\Media\Modules\Admin\Widgets\Navs\FileSubmenu;
use Hirtz\Skeleton\Web\View;
use Hirtz\Skeleton\Widgets\Alert;
use Hirtz\Skeleton\Widgets\Forms\FormContainer;

echo FileHeader::make()
    ->model($file)
    ->content(FileActionDropdown::make()
        ->model($file));

echo FileSubmenu::make()
    ->model($file);

if ($failure = $file->getTransformationFailure()) {
    echo Alert::make()
        ->warning()
        ->content(ImageProcessor::isResourceLimitError($failure)
            ? Yii::t('media', 'FILE_WARNING_TOO_LARGE_FOR_TRANSFORMATIONS')
            : Yii::t('media', 'FILE_WARNING_TRANSFORMATIONS_FAILED'));
}

echo FormContainer::make()
    ->form(FileActiveForm::make()
        ->model($file));
