<?php

declare(strict_types=1);

namespace Hirtz\Media\modules\admin\widgets\buttons;

use Hirtz\Skeleton\html\Button;
use Hirtz\Skeleton\html\Form;
use Hirtz\Skeleton\html\TextInput;
use Hirtz\Skeleton\html\traits\TagLabelTrait;
use Hirtz\Skeleton\html\traits\TagUrlTrait;
use Hirtz\Skeleton\widgets\Modal;
use Hirtz\Skeleton\widgets\Widget;
use Stringable;
use Yii;
use yii\helpers\Url;

class FileImportButton extends Widget
{
    use TagLabelTrait;
    use TagUrlTrait;

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
