<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\forms\fields;

use davidhirtz\yii2\media\helpers\AspectRatio;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\skeleton\html\Div;
use davidhirtz\yii2\skeleton\html\Img;
use Stringable;

class FilePreview implements Stringable
{
    public function __construct(protected File $file, protected array $attributes = [])
    {
    }

    public function __toString(): string
    {
        if (!$this->file->hasPreview()) {
            return '';
        }

        $image = Img::make()
            ->src($this->file->getUrl())
            ->attributes($this->attributes)
            ->addClass('img-transparent');

        return Div::make()
            ->html($image)
            ->attribute('style', [
                'style' => 'position: relative;',
                'aspect-ratio' => new AspectRatio($this->file),
                'max-width' => $this->file->width ? "min(100%,{$this->file->width}px)" : null,
                'max-height' => '70svh',
            ])
            ->render();
    }
}
