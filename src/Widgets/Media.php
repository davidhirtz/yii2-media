<?php

declare(strict_types=1);

namespace Hirtz\Media\Widgets;

use Closure;
use Hirtz\Media\Helpers\AspectRatio;
use Hirtz\Media\Helpers\Size;
use Hirtz\Media\Models\Interfaces\AssetInterface;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Html\Img;
use Hirtz\Skeleton\Html\Picture;
use Hirtz\Skeleton\Html\Source;
use Hirtz\Skeleton\Widgets\Widget;
use Override;
use Stringable;

class Media extends Widget
{
    use ModuleTrait;

    protected AssetInterface $asset;

    protected bool $aspectRatio = false;
    protected ?string $extension = 'avif';
    protected ?string $fetchPriority = null;
    protected bool $lazyLoading = true;
    protected bool $omitUnnecessaryPictureTag = true;
    /**
     * @var list<string>|null
     */
    protected ?array $transformations = null;

    /**
     * @var list<string>|false|null
     */
    protected array|false|null $transformationExtensions = null;

    /**
     * @var list<Size|string>
     */
    protected array $sizes = [];

    /**
     * @var list<Closure>|null
     */
    private ?array $imageClosures = null;

    /**
     * @var list<Closure>|null
     */
    private ?array $pictureClosures = null;

    #[Override]
    public function configure(): void
    {
        $this->sizes = $this->sizes ?: array_filter([$this->asset->getSizes()]);
        $this->transformationExtensions ??= static::getModule()->getTransformationExtensions();

        // The `<img>` falls back like the `<picture>` sources: to the next format the server writes, else the file's own
        if ($this->extension && !static::getModule()->getImageProcessor()->canEncode($this->extension)) {
            $this->extension = ($this->transformationExtensions ?: [])[0] ?? null;
        }
        $this->transformations ??= $this->asset->getTransformationNames();

        parent::configure();
    }

    public function asset(AssetInterface $asset): static
    {
        $this->asset = $asset;
        return $this;
    }

    public function aspectRatio(bool $aspectRatio): static
    {
        $this->aspectRatio = $aspectRatio;
        return $this;
    }

    public function extension(?string $extension): static
    {
        $this->extension = $extension;
        return $this;
    }

    public function fetchPriority(?string $fetchPriority): static
    {
        $this->fetchPriority = $fetchPriority;
        return $this;
    }

    /**
     * @param Closure(Img): Img $image
     */
    public function image(Closure $image): static
    {
        $this->imageClosures[] = $image;
        return $this;
    }

    public function lazyLoading(bool $lazyLoading = true): static
    {
        $this->lazyLoading = $lazyLoading;
        return $this;
    }

    public function omitUnnecessaryPictureTag(bool $omit): static
    {
        $this->omitUnnecessaryPictureTag = $omit;
        return $this;
    }

    /**
     * @param Closure(Picture): Picture $picture
     */
    public function picture(Closure $picture): static
    {
        $this->pictureClosures[] = $picture;
        return $this;
    }

    public function sizes(Size|string ...$sizes): static
    {
        $this->sizes = array_values($sizes);
        return $this;
    }

    /**
     * @param list<string>|null $transformations
     */
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
        if ($this->omitUnnecessaryPictureTag && $this->extension && !$this->pictureClosures) {
            return $this->renderImage($this->extension);
        }

        $picture = Picture::make();
        $extension = $this->extension;

        if ($this->transformationExtensions && $this->asset->file->isTransformableImage()) {
            foreach ($this->transformationExtensions as $sourceExtension) {
                $picture->addContent($this->renderTransformationSource($sourceExtension));
            }

            $extension = null;
        }

        $picture->addContent($this->renderImage($extension));

        return $this->evaluate($this->pictureClosures, $picture);
    }

    protected function renderImage(?string $extension): string|Stringable
    {
        $image = Img::make()
            ->alt($this->asset->getAltText())
            ->fetchPriority($this->getFetchPriority())
            ->loading($this->getLoading())
            ->sizes(...$this->sizes);

        $srcset = $this->asset->getSrcset($this->transformations, $extension);
        $image = $srcset ? $image->srcset($srcset) : $image->src($this->asset->file->getUrl());

        if ($this->aspectRatio) {
            $image->addStyle(['aspect-ratio' => $this->getAspectRatio()]);
        }

        return $this->evaluate($this->imageClosures, $image);
    }

    protected function getLoading(): ?string
    {
        return $this->asset->getLoading() ?? ($this->lazyLoading ? 'lazy' : null);
    }

    protected function getFetchPriority(): ?string
    {
        return $this->asset->getFetchPriority() ?? $this->fetchPriority;
    }

    protected function getAspectRatio(): ?string
    {
        return $this->asset->file->hasDimensions() ? (string)new AspectRatio($this->asset->file) : null;
    }

    protected function renderTransformationSource(string $extension): ?Stringable
    {
        $srcset = $this->asset->getSrcset($this->transformations, $extension);

        return $srcset
            ? Source::make()
                ->sizes(...$this->sizes)
                ->srcset($srcset)
                ->type("image/$extension")
            : null;
    }
}
