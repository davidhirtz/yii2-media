<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Buttons;

use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Html\Form;
use Hirtz\Skeleton\Html\TextInput;
use Hirtz\Skeleton\Widgets\Buttons\Button;
use Hirtz\Skeleton\Widgets\Modal;
use Hirtz\Skeleton\Widgets\Traits\LabelTrait;
use Hirtz\Skeleton\Widgets\Traits\UrlTrait;
use Hirtz\Skeleton\Widgets\Widget;
use Stringable;
use Yii;

;

class FileImportButton extends Widget
{
    use LabelTrait;
    use UrlTrait;

    protected function renderContent(): string|Stringable
    {
        $form = Form::make()
            ->attribute('hx-post', Url::toRoute($this->url))
            ->attribute('hx-swap', 'outerHTML show:window:top')
            ->content(TextInput::make()
                ->name('url')
                ->type('url')
                ->placeholder(Yii::t('media', 'Link'))
                ->required());

        $modal = Modal::make()
            ->title(Yii::t('media', 'Import file from URL'))
            ->content($form)
            ->footer(Button::make()
                ->primary()
                ->type('submit')
                ->attribute('form', $form->getId())
                ->text($this->label));

        return Button::make()
            ->primary()
            ->icon('cloud-upload-alt')
            ->text($this->label)
            ->modal($modal)
            ->render();
    }
}
