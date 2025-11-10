<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\buttons;

use davidhirtz\yii2\skeleton\html\Button;
use davidhirtz\yii2\skeleton\html\Form;
use davidhirtz\yii2\skeleton\html\Input;
use davidhirtz\yii2\skeleton\html\Modal;
use Stringable;
use Yii;
use yii\helpers\Url;

readonly class ImportFileButton implements Stringable
{
    public function __construct(
        private string $label,
        private string|array $url,
    ) {
    }

    public function render(): string
    {
        $form = Form::make()
            ->attribute('hx-post', Url::toRoute($this->url))
            ->attribute('hx-swap', 'outerHTML show:window:top')
            ->html(Input::make()
                ->name('url')
                ->type('url')
                ->placeholder(Yii::t('media', 'Link'))
                ->required());

        $modal = Modal::make()
            ->title(Yii::t('media', 'Import file from URL'))
            ->html($form)
            ->footer(Button::primary()
                ->type('submit')
                ->form($form)
                ->text($this->label));

        return Button::primary()
            ->icon('cloud-upload-alt')
            ->text($this->label)
            ->modal($modal)
            ->render();
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
