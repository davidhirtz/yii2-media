<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Buttons;

use Hirtz\Skeleton\Html\Form;
use Hirtz\Skeleton\Html\Input;
use Hirtz\Skeleton\Html\TextInput;
use Hirtz\Skeleton\Upload\Upload;
use Hirtz\Skeleton\Widgets\Buttons\ConfirmButton;
use Override;
use Yii;

class FileImportButton extends ConfirmButton
{
    protected bool $pushHistory = false;

    #[Override]
    public function isVisible(): bool
    {
        return Upload::getComponent()->enableStreamUploads && parent::isVisible();
    }

    #[Override]
    protected function configure(): void
    {
        $this->icon ??= 'cloud-upload-alt';
        $this->title ??= Yii::t('media', 'FILE_IMPORT_IMPORT_FILE_FROM_URL');

        parent::configure();
    }

    #[Override]
    protected function getForm(): Form
    {
        return Form::make()
            // The server fetches the file while the request is open, which `includes/busy.ts` says on screen.
            ->attribute('data-busy', true)
            ->attribute('hx-swap', 'outerHTML show:top')
            ->content($this->getInput());
    }

    protected function getInput(): Input
    {
        return TextInput::make()
            ->class('input')
            ->name('url')
            ->type('url')
            ->placeholder(Yii::t('media', 'FILE_IMPORT_LINK'))
            ->required();
    }
}
