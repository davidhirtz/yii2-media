<?php

declare(strict_types=1);

namespace Hirtz\Media\Widgets;

use Hirtz\Media\Helpers\Html;
use Hirtz\Media\Helpers\Srcset;
use Hirtz\Media\Models\Interfaces\AssetInterface;
use Hirtz\Skeleton\Helpers\ArrayHelper;
use Hirtz\Skeleton\Html\Img;
use Hirtz\Skeleton\Widgets\Widget;
use Override;
use Stringable;

class Picture extends Widget
{
    protected AssetInterface $asset;

    protected array|string|null $sizes = null;
    protected ?array $transformations = null;

    protected array $imgAttributes = [];
    protected array $pictureAttributes = [];
    protected array $webpAttributes = [];

    protected string $defaultImageLoading = 'lazy';
    protected bool $enableWebpTransformations = true;
    protected bool $omitUnnecessaryPictureTag = true;

    public function asset(AssetInterface $asset): static
    {
        $this->asset = $asset;
        return $this;
    }

    public function imgAttributes(array $attributes): static
    {
        $this->imgAttributes = $attributes;
        return $this;
    }

    public function pictureAttributes(array $attributes): static
    {
        $this->pictureAttributes = $attributes;
        return $this;
    }

    public function omitUnnecessaryPictureTag(bool $omit): static
    {
        $this->omitUnnecessaryPictureTag = $omit;
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

    public function webpAttributes(array $attributes): static
    {
        $this->webpAttributes = $attributes;
        return $this;
    }

    #[Override]
    public function configure(): void
    {
        $this->sizes ??= $this->asset->getSizes();
        $this->transformations ??= $this->asset->getTransformationNames();

        parent::configure();
    }

    #[Override]
    protected function renderContent(): string|Stringable
    {
        return $this->getPictureTag();
    }

    public function getPictureTag(): string|Stringable
    {
        $hasWebp = $this->enableWebpTransformations
            && $this->transformations
            && $this->asset->file->isTransformableImage();

        $image = $this->getImageTag();

        if ($this->omitUnnecessaryPictureTag && !$hasWebp && !$this->pictureAttributes) {
            return $image;
        }

        $source = $hasWebp ? $this->getWebpSourceTag() : '';

        return Hirtz\Skeleton\Html\Picture::make()
            ->attributes($this->pictureAttributes)
            ->content($source)
            ->addContent($image);
    }

    public function getImageTag(): string|Stringable
    {
        $srcset = $this->asset->getSrcset($this->transformations);
        Srcset::addHtmlAttributes($this->imgAttributes, $srcset, $this->sizes, $this->asset->file->getUrl());

        $this->imgAttributes['alt'] ??= $this->asset->getAltText();
        $this->imgAttributes['loading'] ??= $this->defaultImageLoading;

        return Img::make()
            ->attributes($this->imgAttributes);
    }

    public function getWebpSourceTag(): string
    {
        $srcset = $this->asset->getSrcset($this->transformations, 'webp');

        if (!$srcset) {
            return '';
        }

        Srcset::addHtmlAttributes($this->webpAttributes, $srcset, $this->sizes);

        // `<source src>` with a `<picture>` parent is invalid, change it to `srcset`
        $src = ArrayHelper::remove($this->webpAttributes, 'src');
        $this->webpAttributes['srcset'] ??= $src;

        $this->webpAttributes['type'] ??= 'image/webp';

        return Html::tag('source', '', $this->webpAttributes);
    }
}
