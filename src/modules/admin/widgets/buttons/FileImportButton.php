<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\buttons;

use davidhirtz\yii2\skeleton\html\Button;
use davidhirtz\yii2\skeleton\html\Form;
use davidhirtz\yii2\skeleton\html\TextInput;
use davidhirtz\yii2\skeleton\html\traits\TagLabelTrait;
use davidhirtz\yii2\skeleton\html\traits\TagUrlTrait;
use davidhirtz\yii2\skeleton\widgets\Modal;
use davidhirtz\yii2\skeleton\widgets\Widget;
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
