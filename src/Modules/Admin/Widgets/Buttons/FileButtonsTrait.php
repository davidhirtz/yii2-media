<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Buttons;

use Stringable;
use Yii;

trait FileButtonsTrait
{
    protected function getFileUploadButton(): Stringable
    {
        return FileUploadButton::make()
            ->label(Yii::t('media', 'Upload Files'))
            ->url($this->getFileUploadRoute())
            ->target('#files');
    }

    protected function getFileImportButton(): Stringable
    {
        return FileImportButton::make()
            ->label(Yii::t('media', 'Import File'))
            ->url($this->getFileUploadRoute());
    }

    abstract protected function getFileUploadRoute(): array;
}
