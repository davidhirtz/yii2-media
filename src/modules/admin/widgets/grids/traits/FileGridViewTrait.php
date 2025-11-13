<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\grids\traits;

use davidhirtz\yii2\media\modules\admin\widgets\buttons\ImportFileButton;
use davidhirtz\yii2\media\modules\admin\widgets\buttons\UploadFileButton;
use Stringable;
use Yii;

trait FileGridViewTrait
{
    protected function getUploadFileButton(): Stringable
    {
        return Yii::createObject(UploadFileButton::class, [
            Yii::t('media', 'Upload Files'),
            $this->getFileUploadRoute(),
            '#' . $this->getId(),
            true,
        ]);
    }

    protected function getImportFileButton(): Stringable
    {
        return Yii::createObject(ImportFileButton::class, [
            Yii::t('media', 'Import'),
            $this->getFileUploadRoute(),
        ]);
    }
}
