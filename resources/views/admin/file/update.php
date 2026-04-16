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
use Hirtz\Media\Modules\Admin\Widgets\Grids\FileRelationGridContainer;
use Hirtz\Media\Modules\Admin\Widgets\Grids\TransformationGridView;
use Hirtz\Media\Modules\Admin\Widgets\Navs\FileHeader;
use Hirtz\Skeleton\Web\View;
use Hirtz\Skeleton\Widgets\Forms\FormContainer;
use Hirtz\Skeleton\Widgets\Grids\GridContainer;

echo FileHeader::make()
    ->model($file);

echo FormContainer::make()
    ->title(Yii::t('media', 'Edit File'))
    ->form(FileActiveForm::make()
        ->model($file));

echo FileRelationGridContainer::make()
    ->file($file);

if ($file->transformation_count) {
    echo GridContainer::make()
        ->title(Yii::t('media', 'Transformations'))
        ->grid(TransformationGridView::make()
            ->file($file));
}
