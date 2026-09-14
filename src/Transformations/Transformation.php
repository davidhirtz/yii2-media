<?php

declare(strict_types=1);

namespace Hirtz\Media\Transformations;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\FileTransformation;
use Hirtz\Skeleton\Base\Traits\ContainerConfigurationTrait;
use Imagine\Image\ImageInterface;
use yii\base\InvalidConfigException;

/**
 * A transformation preset: module configuration the file model and the media widget consume, not a model attribute
 * definition. {@see FileTransformation} is the row it produces.
 */
class Transformation
{
    use ContainerConfigurationTrait;

    public const string NAME_ADMIN = 'admin';
    public const string NAME_OPEN_GRAPH = 'og';

    /**
     * A self-describing name: `w_400`, `h_200`, `w_300@2`, `w_200,h_300@2` — a dimension each, with an optional PPI
     * modifier after the `@`, per dimension or for both.
     */
    private const string NAME_PATTERN = '/^([wh])_(\d+)(?:@(\d+(?:\.\d+)?|\.\d+))?$/';
    private const string NAME_MODIFIER_PATTERN = '/^(.*)@(\d+(?:\.\d+)?|\.\d+)$/';

    protected ?int $width = null;
    protected ?int $height = null;
    protected bool $keepAspectRatio = false;
    protected bool $scaleUp = false;
    protected string|int|null $backgroundColor = null;
    protected ?int $backgroundAlpha = null;
    protected int $jpegQuality = 75;
    protected int $pngCompressionLevel = 7;
    protected int $webpQuality = 80;
    protected string $resolutionUnits = ImageInterface::RESOLUTION_PIXELSPERINCH;
    protected int $resolutionX = 72;
    protected int $resolutionY = 72;

    public function __construct(public readonly string $name)
    {
        if ($name === '') {
            throw new InvalidConfigException('A transformation needs a name.');
        }
    }

    /**
     * The dimensions a self-describing name asks for, or `null` for a name that does not describe any.
     */
    public static function fromName(string $name): ?static
    {
        $dimensions = $name;
        $modifier = 1.0;

        if (preg_match(self::NAME_MODIFIER_PATTERN, $name, $matches)) {
            $dimensions = $matches[1];
            $modifier = (float)$matches[2];
        }

        $transformation = null;

        foreach (explode(',', $dimensions) as $dimension) {
            if (!preg_match(self::NAME_PATTERN, $dimension, $matches)) {
                continue;
            }

            $size = (int)ceil((int)$matches[2] * (float)($matches[3] ?? 1) * $modifier);
            $transformation ??= static::make($name);

            if ($matches[1] === 'w') {
                $transformation->width($size);
            } else {
                $transformation->height($size);
            }
        }

        return $transformation;
    }

    public function width(?int $width): static
    {
        $this->width = $width;
        return $this;
    }

    public function height(?int $height): static
    {
        $this->height = $height;
        return $this;
    }

    public function keepAspectRatio(bool $keepAspectRatio = true): static
    {
        $this->keepAspectRatio = $keepAspectRatio;
        return $this;
    }

    public function scaleUp(bool $scaleUp = true): static
    {
        $this->scaleUp = $scaleUp;
        return $this;
    }

    public function backgroundColor(string|int|null $backgroundColor): static
    {
        $this->backgroundColor = $backgroundColor;
        return $this;
    }

    public function backgroundAlpha(?int $backgroundAlpha): static
    {
        $this->backgroundAlpha = $backgroundAlpha;
        return $this;
    }

    public function jpegQuality(int $jpegQuality): static
    {
        $this->jpegQuality = $jpegQuality;
        return $this;
    }

    public function pngCompressionLevel(int $pngCompressionLevel): static
    {
        $this->pngCompressionLevel = $pngCompressionLevel;
        return $this;
    }

    public function webpQuality(int $webpQuality): static
    {
        $this->webpQuality = $webpQuality;
        return $this;
    }

    public function resolution(int $x, ?int $y = null, string $units = ImageInterface::RESOLUTION_PIXELSPERINCH): static
    {
        $this->resolutionX = $x;
        $this->resolutionY = $y ?? $x;
        $this->resolutionUnits = $units;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function keepsAspectRatio(): bool
    {
        return $this->keepAspectRatio;
    }

    public function scalesUp(): bool
    {
        return $this->scaleUp;
    }

    public function getBackgroundColor(): string|int|null
    {
        return $this->backgroundColor;
    }

    public function getBackgroundAlpha(): ?int
    {
        return $this->backgroundAlpha;
    }

    /**
     * @return array<string, string|int>
     * @see https://imagine.readthedocs.io/en/stable/usage/introduction.html#save-images
     */
    public function getImageOptions(): array
    {
        return [
            'resolution-units' => $this->resolutionUnits,
            'resolution-x' => $this->resolutionX,
            'resolution-y' => $this->resolutionY,
            'jpeg_quality' => $this->jpegQuality,
            'png_compression_level' => $this->pngCompressionLevel,
            'webp_quality' => $this->webpQuality,
        ];
    }

    /**
     * Whether the file can be transformed without scaling it up.
     */
    public function isApplicableTo(File $file): bool
    {
        if (!$file->isTransformableImage()) {
            return false;
        }

        if ($this->scaleUp) {
            return true;
        }

        $isWidthValid = !$this->width || $this->width <= $file->width;
        $isHeightValid = !$this->height || $this->height <= $file->height;

        return $this->keepAspectRatio && $this->width && $this->height
            ? ($isWidthValid || $isHeightValid)
            : ($isWidthValid && $isHeightValid);
    }

    /**
     * The width the transformed file will have, which is what the srcset descriptor names.
     */
    public function getWidthFor(File $file): int
    {
        if ($this->width) {
            return $this->width;
        }

        return $this->height && $file->height
            ? (int)floor($this->height / $file->height * $file->width)
            : (int)$file->width;
    }
}
