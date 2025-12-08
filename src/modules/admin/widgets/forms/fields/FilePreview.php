<?php

declare(strict_types=1);

namespace Hirtz\Media\modules\admin\widgets\forms\fields;

use Hirtz\Media\helpers\AspectRatio;
use Hirtz\Media\models\File;
use Hirtz\Skeleton\html\Div;
use Hirtz\Skeleton\html\Img;
use Hirtz\Skeleton\html\traits\TagAttributesTrait;
use Hirtz\Skeleton\widgets\traits\ModelWidgetTrait;
use Hirtz\Skeleton\widgets\Widget;
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
