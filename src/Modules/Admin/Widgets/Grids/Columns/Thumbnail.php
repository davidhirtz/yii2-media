<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids\Columns;

use Hirtz\Media\Traits\FilePropertyTrait;
use Hirtz\Skeleton\Html\Img;
use Hirtz\Skeleton\Widgets\Widget;
use Stringable;

class Thumbnail extends Widget
{
    use FilePropertyTrait;

    protected function renderContent(): string|Stringable
    {
        if (!$this->file->hasPreview()) {
            return '';
        }

        $imageUrl = $this->file->getTransformationUrl('admin') ?: $this->file->getUrl();

        return Img::make()
            ->src($imageUrl)
            ->class('img-thumbnail')
            ->loading('lazy');
    }
}
