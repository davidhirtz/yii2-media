<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids\Columns;

use Hirtz\Media\Models\File;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Media\Traits\FilePropertyTrait;
use Hirtz\Skeleton\Html\Div;
use Hirtz\Skeleton\Html\Img;
use Hirtz\Skeleton\Widgets\Widget;
use Stringable;

class Thumbnail extends Widget
{
    use FilePropertyTrait;

    /**
     * @var list<Stringable>
     */
    protected array $badges = [];

    public function badges(Stringable ...$badges): static
    {
        $this->badges = array_values($badges);
        return $this;
    }

    protected function renderContent(): string|Stringable
    {
        $image = $this->renderImage();
        $badges = $this->renderBadges();

        return $image !== '' && $badges
            ? Div::make()
                ->class('thumbnail')
                ->content($image, $badges)
            : $image;
    }

    protected function renderImage(): string|Stringable
    {
        if (!$this->file->hasPreview()) {
            $previewUrl = $this->file->getPreviewUrl();

            return $previewUrl ? Img::make()
                ->src($previewUrl)
                ->class('img-thumbnail')
                ->loading('lazy') : '';
        }

        $extensions = File::getModule()->getTransformationExtensions();
        $extension = $extensions ? File::getModule()->getPreviewExtension() : null;
        $imageUrl = $this->file->getTransformationUrl(Transformation::NAME_ADMIN, $extension) ?: $this->file->getUrl();

        return Img::make()
            ->src($imageUrl)
            ->class('img-thumbnail')
            ->loading('lazy');
    }

    /**
     * @return list<Stringable>
     */
    protected function getBadges(): array
    {
        return $this->badges;
    }

    protected function renderBadges(): ?Stringable
    {
        $badges = $this->getBadges();

        return $badges ? Div::make()
            ->class('thumbnail-badges')
            ->content(...$badges) : null;
    }
}
