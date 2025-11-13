<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\buttons;

use davidhirtz\yii2\media\modules\admin\widgets\forms\FileUploadInputWidget;
use davidhirtz\yii2\skeleton\html\Button;
use Stringable;

readonly class FileUploadButton implements Stringable
{
    public function __construct(
        private string $label,
        private string|array $url,
        private ?string $target = null,
        private bool $multiple = false,
    ) {
    }

    public function render(): string
    {
        $button = Button::make()
            ->primary()
            ->text($this->label)
            ->icon('upload')
            ->render();

        return FileUploadInputWidget::widget([
            'content' => $button,
            'url' => $this->url,
            'target' => $this->target,
            'options' => $this->multiple ? ['multiple' => true] : null,
        ]);
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
