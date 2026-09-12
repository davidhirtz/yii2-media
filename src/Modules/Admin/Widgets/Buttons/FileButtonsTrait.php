<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Buttons;

use Hirtz\Skeleton\Widgets\Buttons\Button;
use Stringable;
use Yii;

trait FileButtonsTrait
{
    protected function getFileUploadButton(): Stringable
    {
        return FileUploadButton::make()
            ->button(fn (Button $button) => $button->addClass('dropdown-item-btn'))
            ->label(Yii::t('media', 'FILE_BUTTONS_UPLOAD_FILES'))
            ->multiple()
            ->url($this->getFileUploadRoute())
            ->target($this->getFileUploadTarget())
            ->selectOob($this->getFileUploadSelectOob());
    }

    protected function getFileImportButton(): Stringable
    {
        return FileImportButton::make()
            ->label(Yii::t('media', 'FILE_BUTTONS_IMPORT_FILE'))
            ->url($this->getFileUploadRoute());
    }

    abstract protected function getFileUploadRoute(): array;

    /**
     * The upload swaps this element with the one of the same id in the response, so it must name a grid that both the
     * page the button sits on and the upload route render.
     */
    abstract protected function getFileUploadTarget(): string;

    /**
     * @return string|null what the response refreshes besides the target, such as a counter that sits outside it
     */
    protected function getFileUploadSelectOob(): ?string
    {
        return null;
    }
}
