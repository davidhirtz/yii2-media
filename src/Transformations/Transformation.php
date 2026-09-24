<?php

declare(strict_types=1);

namespace Hirtz\Media\Transformations;

use Hirtz\Media\Images\ImageProcessor;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\FileTransformation;
use Hirtz\Skeleton\Base\Traits\ContainerConfigurationTrait;
use Intervention\Image\Size;
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
    protected int $webpQuality = 80;
    protected int $avifQuality = 60;
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

    public function webpQuality(int $webpQuality): static
    {
        $this->webpQuality = $webpQuality;
        return $this;
    }

    public function avifQuality(int $avifQuality): static
    {
        $this->avifQuality = $avifQuality;
        return $this;
    }

    /**
     * The resolution in pixels per inch.
     */
    public function resolution(int $x, ?int $y = null): static
    {
        $this->resolutionX = $x;
        $this->resolutionY = $y ?? $x;

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
     * @return array{int, int}
     */
    public function getResolution(): array
    {
        return [$this->resolutionX, $this->resolutionY];
    }

    /**
     * @return array{jpegQuality: int, webpQuality: int, avifQuality: int}
     * @see ImageProcessor::write()
     */
    public function getImageOptions(): array
    {
        return [
            'jpegQuality' => $this->jpegQuality,
            'webpQuality' => $this->webpQuality,
            'avifQuality' => $this->avifQuality,
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
        return $this->getSizeFor($file)[0];
    }

    /**
     * The width and height the transformed file will have, following {@see ImageProcessor::transform()}: both
     * dimensions without `keepAspectRatio()` crop to that aspect ratio (or letterbox to exactly that size with a
     * background color), anything else scales into the box, and never up unless `scaleUp()` says so.
     *
     * @return array{int, int}
     */
    public function getSizeFor(File $file): array
    {
        $fileWidth = (int)$file->width;
        $fileHeight = (int)$file->height;

        if ((!$this->width && !$this->height) || $fileWidth < 1 || $fileHeight < 1) {
            return [$this->width ?? $fileWidth, $this->height ?? $fileHeight];
        }

        if ($this->width && $this->height && !$this->keepAspectRatio) {
            if ($this->scaleUp || $this->backgroundColor !== null) {
                return [$this->width, $this->height];
            }

            $size = (new Size($this->width, $this->height))
                ->contain($fileWidth, $fileHeight)
                ->resizeDown($this->width, $this->height);
        } else {
            $size = new Size($fileWidth, $fileHeight);

            $size = $this->scaleUp
                ? $size->scale($this->width, $this->height)
                : $size->scaleDown($this->width, $this->height);
        }

        return [$size->width(), $size->height()];
    }
}
