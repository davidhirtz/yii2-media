<?php

declare(strict_types=1);

namespace Hirtz\Media\modules\admin\widgets\grids\traits;

use Hirtz\Media\modules\admin\widgets\buttons\FileImportButton;
use Hirtz\Media\modules\admin\widgets\buttons\FileUploadButton;
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
