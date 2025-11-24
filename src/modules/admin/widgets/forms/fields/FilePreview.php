<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\forms\fields;

use davidhirtz\yii2\media\helpers\AspectRatio;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\skeleton\html\Div;
use davidhirtz\yii2\skeleton\html\Img;
use davidhirtz\yii2\skeleton\html\traits\TagAttributesTrait;
use davidhirtz\yii2\skeleton\widgets\traits\ModelWidgetTrait;
use davidhirtz\yii2\skeleton\widgets\Widget;
use Stringable;

/**
 * @property File $model
 */
class FilePreview extends Widget
{
    use TagAttributesTrait;
    use ModelWidgetTrait;

    protected function renderContent(): string|Stringable
    {
        if (!$this->model->hasPreview()) {
            return '';
        }

        $image = Img::make()
            ->src($this->model->getUrl())
            ->attributes($this->attributes)
            ->addClass('img-transparent');

        return Div::make()
            ->content($image)
            ->attribute('style', [
                'style' => 'position: relative;',
                'aspect-ratio' => new AspectRatio($this->model),
                'max-width' => $this->model->width ? "min(100%,{$this->model->width}px)" : null,
                'max-height' => '70svh',
            ])
            ->render();
    }
}
