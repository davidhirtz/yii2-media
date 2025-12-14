<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids\Traits;

use Hirtz\Media\Modules\Admin\Widgets\Buttons\FileImportButton;
use Hirtz\Media\Modules\Admin\Widgets\Buttons\FileUploadButton;
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
