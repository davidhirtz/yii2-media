<?php

declare(strict_types=1);

namespace Hirtz\Media\Widgets;

use Closure;
use Hirtz\Media\Models\Interfaces\AssetInterface;
use Hirtz\Skeleton\Html\Img;
use Hirtz\Skeleton\Html\Source;
use Hirtz\Skeleton\Widgets\Widget;
use Override;
use Stringable;

class Picture extends Widget
{
    protected AssetInterface $asset;

    protected array|string|null $sizes = null;
    protected ?array $transformations = null;

    protected bool $lazyLoading = true;
    protected bool $enableWebpTransformations = true;
    protected bool $enableLegacyFileFormats = false;
    protected bool $omitUnnecessaryPictureTag = true;

    private ?Closure $picture = null;
    private ?Closure $image = null;

    #[Override]
    public function configure(): void
    {
        if ($this->enableWebpTransformations) {
            $this->enableWebpTransformations = $this->transformations && $this->asset->file->isTransformableImage();
        } else {
            $this->enableLegacyFileFormats = true;
        }

        $this->sizes ??= $this->asset->getSizes();
        $this->transformations ??= $this->asset->getTransformationNames();

        parent::configure();
    }

    public function asset(AssetInterface $asset): static
    {
        $this->asset = $asset;
        return $this;
    }

    public function enableLegacyFileFormats(bool $enable): static
    {
        $this->enableLegacyFileFormats = $enable;
        return $this;
    }

    public function image(?Closure $image): static
    {
        $this->image = $image;
        return $this;
    }

    public function lazyLoading(bool $lazyLoading): static
    {
        $this->lazyLoading = $lazyLoading;
        return $this;
    }

    public function omitUnnecessaryPictureTag(bool $omit): static
    {
        $this->omitUnnecessaryPictureTag = $omit;
        return $this;
    }

    public function picture(?Closure $picture): static
    {
        $this->picture = $picture;
        return $this;
    }

    public function sizes(array|string|null $sizes): static
    {
        $this->sizes = $sizes;
        return $this;
    }

    public function transformations(?array $transformations): static
    {
        $this->transformations = $transformations;
        return $this;
    }

    #[Override]
    protected function renderContent(): string|Stringable
    {
        return $this->renderPicture();
    }

    protected function renderPicture(): string|Stringable
    {
        $image = $this->renderImage();

        if ($this->omitUnnecessaryPictureTag && !$this->enableWebpTransformations && $this->picture === null) {
            return $image;
        }

        $source = $this->enableWebpTransformations && $this->enableLegacyFileFormats
            ? $this->renderWebpSource()
            : null;

        $picture = \Hirtz\Skeleton\Html\Picture::make()
            ->content($source)
            ->addContent($image);

        return $this->picture !== null ? call_user_func($this->picture, $picture) : $picture;
    }

    protected function renderImage(): string|Stringable
    {
        $image = Img::make()
            ->alt($this->asset->getAltText())
            ->loading($this->lazyLoading ? 'lazy' : null)
            ->sizes(...(array)$this->sizes);

        $srcset = $this->asset->getSrcset($this->transformations, $this->enableLegacyFileFormats ? null : 'webp');
        $image = $srcset ? $image->srcset($srcset) : $image->src($this->asset->file->getUrl());

        return $this->image !== null ? call_user_func($this->image, $image) : $image;
    }

    protected function renderWebpSource(): ?Stringable
    {
        $srcset = $this->asset->getSrcset($this->transformations, 'webp');

        return $srcset
            ? Source::make()
                ->sizes(...(array)$this->sizes)
                ->srcset($srcset)
                ->type('image/webp')
            : null;
    }
}
