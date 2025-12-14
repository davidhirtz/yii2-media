<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids\Columns;

use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Html\Img;
use Hirtz\Skeleton\Widgets\Widget;
use Stringable;

class Thumbnail extends Widget
{
    protected File $file;

    public function file(File $file): static
    {
        $this->file = $file;
        return $this;
    }

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
