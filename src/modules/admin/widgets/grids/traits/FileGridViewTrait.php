<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\grids\traits;

use davidhirtz\yii2\media\modules\admin\widgets\buttons\FileImportButton;
use davidhirtz\yii2\media\modules\admin\widgets\buttons\FileUploadButton;
use Stringable;
use Yii;

trait FileGridViewTrait
{
    protected function getFileUploadButton(): Stringable
    {
        return Yii::createObject(FileUploadButton::class, [
            Yii::t('media', 'Upload Files'),
            $this->getFileUploadRoute(),
            '#' . $this->getId(),
            true,
        ]);
    }

    protected function getFileImportButton(): Stringable
    {
        return Yii::createObject(FileImportButton::class, [
            Yii::t('media', 'Import'),
            $this->getFileUploadRoute(),
        ]);
    }
}
