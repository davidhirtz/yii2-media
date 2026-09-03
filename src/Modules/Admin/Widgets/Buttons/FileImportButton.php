<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Buttons;

use Hirtz\Skeleton\I18n\Lang;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Html\Form;
use Hirtz\Skeleton\Html\Input;
use Hirtz\Skeleton\Html\TextInput;
use Hirtz\Skeleton\Widgets\Buttons\Button;
use Hirtz\Skeleton\Widgets\Modal;
use Hirtz\Skeleton\Widgets\Traits\LabelTrait;
use Hirtz\Skeleton\Widgets\Traits\UrlTrait;
use Hirtz\Skeleton\Widgets\Widget;
use Override;
use Stringable;
use Yii;

class FileImportButton extends Widget
{
    use LabelTrait;
    use UrlTrait;

    #[Override]
    protected function renderContent(): string|Stringable
    {
        return $this->getButton();
    }

    protected function getButton(): Stringable
    {
        return Button::make()
            ->primary()
            ->icon('cloud-upload-alt')
            ->text($this->label)
            ->modal($this->getModal());
    }

    protected function getModal(): Modal
    {
        $form = $this->getForm();

        $button = Button::make()
            ->primary()
            ->type('submit')
            ->attribute('form', $form->getId())
            ->text($this->label);

        return Modal::make()
            ->title(Lang::t('media', 'FILE_IMPORT_IMPORT_FILE_FROM_URL'))
            ->content($form)
            ->footer($button);
    }

    protected function getForm(): Form
    {
        return Form::make()
            ->attribute('hx-post', Url::toRoute($this->url))
            ->attribute('hx-swap', 'outerHTML show:window:top')
            ->content($this->getInput());
    }

    protected function getInput(): Input
    {
        return TextInput::make()
            ->class('input')
            ->name('url')
            ->type('url')
            ->placeholder(Lang::t('media', 'FILE_IMPORT_LINK'))
            ->required();
    }
}
