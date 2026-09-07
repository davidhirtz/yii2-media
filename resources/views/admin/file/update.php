<?php

declare(strict_types=1);

/**
 * @see FileController::actionUpdate()
 *
 * @var View $this
 * @var File $file
 */

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Controllers\FileController;
use Hirtz\Media\Modules\Admin\Widgets\Forms\FileActiveForm;
use Hirtz\Media\Modules\Admin\Widgets\Navs\FileHeader;
use Hirtz\Media\Modules\Admin\Widgets\Navs\FileSubmenu;
use Hirtz\Skeleton\Web\View;
use Hirtz\Skeleton\Widgets\Forms\FormContainer;

echo FileHeader::make()
    ->model($file);

echo FileSubmenu::make()
    ->model($file);

echo FormContainer::make()
    ->title(Yii::t('media', 'Edit File'))
    ->form(FileActiveForm::make()
        ->model($file));
