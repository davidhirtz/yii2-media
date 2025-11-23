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
        return FileUploadButton::make()
            ->url($this->getFileUploadRoute())
            ->target('#' . $this->getId());
    }

    protected function getFileImportButton(): Stringable
    {
        return FileImportButton::make()
            ->label(Yii::t('media', 'Import'))
            ->url($this->getFileUploadRoute());
    }
}
