<?php

declare(strict_types=1);

namespace Hirtz\Media\modules\admin\widgets\grids\columns;

use Hirtz\Media\models\File;
use Hirtz\Skeleton\html\Img;
use Hirtz\Skeleton\widgets\Widget;
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
