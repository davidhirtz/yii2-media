<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Forms\Fields;

use Hirtz\Media\helpers\AspectRatio;
use Hirtz\Media\models\File;
use Hirtz\Skeleton\Html\Div;
use Hirtz\Skeleton\Html\Img;
use Hirtz\Skeleton\Html\Traits\TagAttributesTrait;
use Hirtz\Skeleton\Widgets\Traits\ModelWidgetTrait;
use Hirtz\Skeleton\Widgets\Widget;
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
