<?php

declare(strict_types=1);

namespace Hirtz\Media\Images;

use Hirtz\Media\Transformations\Transformation;
use Imagick;
use Intervention\Image\Colors\Cmyk\Colorspace as CmykColorspace;
use Intervention\Image\Colors\Rgb\Colorspace as RgbColorspace;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Exceptions\ImageException;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Yii;
use yii\base\InvalidArgumentException;

/**
 * Reads, transforms and writes images through Intervention Image. Every image is oriented by its EXIF data when read,
 * so what is written is upright. {@see \Hirtz\Media\Module::$imageProcessor} swaps the driver.
 */
class ImageProcessor
{
    private ?ImageManagerInterface $manager = null;

    /**
     * @param class-string<DriverInterface> $driver
     */
    public function __construct(protected string $driver = Driver::class)
    {
    }

    /**
     * A path a stream wrapper serves (S3) is read through the stream, which the driver's own reader cannot open.
     */
    public function read(string $path): ImageInterface
    {
        $path = (string)Yii::getAlias($path);

        if (stream_is_local($path)) {
            return $this->getManager()->decodePath($path);
        }

        $stream = @fopen($path, 'r');

        if ($stream === false) {
            throw new InvalidArgumentException("Remote file \"$path\" could not be opened.");
        }

        try {
            return $this->getManager()->decodeStream($stream);
        } finally {
            fclose($stream);
        }
    }

    public function transform(string $path, Transformation $transformation): ImageInterface
    {
        $image = $this->read($path);

        $width = $transformation->getWidth();
        $height = $transformation->getHeight();
        $scaleUp = $transformation->scalesUp();

        if ($width && $height && !$transformation->keepsAspectRatio()) {
            $background = $this->getBackground($transformation);

            $image = match (true) {
                $background === null => $scaleUp ? $image->cover($width, $height) : $image->coverDown($width, $height),
                $scaleUp => $image->contain($width, $height, $background),
                default => $image->containDown($width, $height, $background),
            };
        } elseif ($width || $height) {
            $image = $scaleUp ? $image->scale($width, $height) : $image->scaleDown($width, $height);
        }

        [$x, $y] = $transformation->getResolution();

        return $image->setResolution($x, $y);
    }

    /**
     * Encodes the image by the path's extension. A quality the options do not name is the source's, where the driver
     * knows it.
     *
     * @param array<string, mixed> $options `jpegQuality`, `webpQuality`, `avifQuality`
     * @see Transformation::getImageOptions()
     */
    public function write(ImageInterface $image, string $path, array $options = []): void
    {
        $this->normalizeColorProfile($image);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $quality = match ($extension) {
            'jpg', 'jpeg' => $options['jpegQuality'] ?? null,
            'webp' => $options['webpQuality'] ?? null,
            'avif' => $options['avifQuality'] ?? null,
            default => false,
        };

        if ($quality === null) {
            $quality = $this->getSourceQuality($image);
        }

        $encoded = is_int($quality)
            ? $image->encodeUsingFileExtension($extension, quality: $quality)
            : $image->encodeUsingFileExtension($extension);

        // Imagick's own writer does not support stream wrappers
        if (file_put_contents($path, (string)$encoded) === false) {
            throw new InvalidArgumentException("Image \"$path\" could not be written.");
        }
    }

    /**
     * A profile has to describe the pixels it comes with: CMYK is converted to RGB, and a profile that is not RGB is
     * dropped. Intervention relabels a grey image as RGB but keeps its Gray profile, and Chrome refuses to decode a
     * colour AVIF carrying one (#259).
     */
    protected function normalizeColorProfile(ImageInterface $image): void
    {
        try {
            if ($image->colorspace() instanceof CmykColorspace) {
                $image->setColorspace(new RgbColorspace());
            }

            $profile = $image->profile()->toString();
        } catch (ImageException) {
            return;
        }

        if (substr($profile, 16, 4) !== 'RGB ') {
            $image->removeProfile();
        }
    }

    protected function getSourceQuality(ImageInterface $image): ?int
    {
        $native = $image->core()->native();
        $quality = $native instanceof Imagick ? $native->getImageCompressionQuality() : 0;

        return $quality > 0 && $quality <= 100 ? $quality : null;
    }

    /**
     * The background color with the alpha folded in, as Intervention takes it: `backgroundAlpha()` is the opacity in
     * percent.
     */
    protected function getBackground(Transformation $transformation): ?string
    {
        $color = $transformation->getBackgroundColor();

        if ($color === null) {
            return null;
        }

        $color = is_int($color) ? sprintf('%06x', $color) : ltrim($color, '#');
        $alpha = $transformation->getBackgroundAlpha();

        if (!preg_match('/^[\da-f]{3}$|^[\da-f]{6}$/i', $color)) {
            return $color;
        }

        if (strlen($color) === 3) {
            $color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
        }

        return "#$color" . ($alpha === null ? '' : sprintf('%02x', (int)round(max(0, min(100, $alpha)) * 2.55)));
    }

    protected function getManager(): ImageManagerInterface
    {
        return $this->manager ??= ImageManager::usingDriver($this->driver);
    }
}
