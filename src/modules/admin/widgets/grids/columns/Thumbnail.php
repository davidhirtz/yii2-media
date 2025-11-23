<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\grids\columns;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\skeleton\html\Img;
use davidhirtz\yii2\skeleton\widgets\Widget;
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
