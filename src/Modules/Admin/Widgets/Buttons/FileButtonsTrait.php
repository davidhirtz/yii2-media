<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Buttons;

use Hirtz\Skeleton\I18n\Lang;
use Hirtz\Skeleton\Widgets\Buttons\Button;
use Stringable;

trait FileButtonsTrait
{
    protected function getFileUploadButton(): Stringable
    {
        return FileUploadButton::make()
            ->button(fn (Button $button) => $button->addClass('dropdown-item-btn'))
            ->label(Lang::t('media', 'FILE_BUTTONS_UPLOAD_FILES'))
            ->multiple()
            ->url($this->getFileUploadRoute())
            ->target('#files');
    }

    protected function getFileImportButton(): Stringable
    {
        return FileImportButton::make()
            ->label(Lang::t('media', 'FILE_BUTTONS_IMPORT_FILE'))
            ->url($this->getFileUploadRoute());
    }

    abstract protected function getFileUploadRoute(): array;
}
