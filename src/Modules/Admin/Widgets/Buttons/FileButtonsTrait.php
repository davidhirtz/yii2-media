<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Buttons;

use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Widgets\Buttons\Button;
use Stringable;
use Yii;

trait FileButtonsTrait
{
    protected function getFileUploadButton(): Stringable
    {
        return FileUploadButton::make()
            ->button(fn (Button $button) => $button->addClass('dropdown-item-btn'))
            ->label(Yii::t('media', 'FILE_UPLOAD_MULTIPLE_BUTTON'))
            ->multiple()
            ->url($this->getFileUploadRoute())
            ->target($this->getFileUploadTarget())
            ->selectOob($this->getFileUploadSelectOob())
            ->roles([File::AUTH_FILE]);
    }

    protected function getFileImportButton(): Stringable
    {
        return FileImportButton::make()
            ->label(Yii::t('media', 'FILE_IMPORT_BUTTON'))
            ->url($this->getFileUploadRoute())
            ->roles([File::AUTH_FILE]);
    }

    abstract protected function getFileUploadRoute(): array;

    /**
     * The upload swaps the element of this id, so both the page and the upload route must render that grid.
     */
    abstract protected function getFileUploadTarget(): string;

    /**
     * What the response refreshes besides the target, such as a counter outside it.
     */
    protected function getFileUploadSelectOob(): ?string
    {
        return null;
    }
}
