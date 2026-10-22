<?php

declare(strict_types=1);

namespace Hirtz\Media\Images;

use Hirtz\Media\Transformations\Transformation;
use Closure;
use Imagick;
use ImagickException;
use Intervention\Image\Colors\Cmyk\Colorspace as CmykColorspace;
use Intervention\Image\Colors\Rgb\Colorspace as RgbColorspace;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Drivers\Imagick\Modifiers\StripMetaModifier;
use Intervention\Image\Exceptions\ImageDecoderException;
use Intervention\Image\Exceptions\ImageException;
use Intervention\Image\ImageManager;
use Intervention\Image\Size;
use Intervention\Image\Interfaces\DriverInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Throwable;
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
     * @var array<string, bool>
     */
    private array $encodable = [];

    /**
     * @var array<string, bool>
     */
    private array $qualityApplied = [];

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
            $this->assertRasterImage((string)@file_get_contents($path, length: 16));

            try {
                return $this->getManager()->decodePath($path);
            } catch (ImageDecoderException $exception) {
                throw $this->explainDecoderException($exception, static fn (Imagick $imagick) => $imagick->readImage($path));
            }
        }

        $stream = @fopen($path, 'r');

        if ($stream === false) {
            throw new InvalidArgumentException("Remote file \"$path\" could not be opened.");
        }

        try {
            $contents = (string)stream_get_contents($stream);
        } finally {
            fclose($stream);
        }

        $this->assertRasterImage($contents);

        try {
            return $this->getManager()->decodeBinary($contents);
        } catch (ImageDecoderException $exception) {
            throw $this->explainDecoderException($exception, static fn (Imagick $imagick) => $imagick->readImageBlob($contents));
        }
    }

    /**
     * Intervention drops ImageMagick's own reason — a resource limit of the host's policy, a damaged file — which is
     * what the log needs to tell them apart.
     *
     * @param Closure(Imagick): mixed $read
     */
    protected function explainDecoderException(ImageDecoderException $exception, Closure $read): ImageDecoderException
    {
        if ($this->isImagick()) {
            try {
                $read(new Imagick());
            } catch (ImagickException $reason) {
                return new ImageDecoderException("{$exception->getMessage()}: {$reason->getMessage()}", previous: $reason);
            }
        }

        return $exception;
    }

    /**
     * ImageMagick picks its coder by the content, not the name: SVG, MVG and the other vector and script coders
     * are refused before it sees them (#492).
     */
    protected function assertRasterImage(string $header): void
    {
        $isRaster = str_starts_with($header, "\xFF\xD8\xFF")
            || str_starts_with($header, "\x89PNG\r\n\x1A\n")
            || str_starts_with($header, 'GIF8')
            || (str_starts_with($header, 'RIFF') && substr($header, 8, 4) === 'WEBP')
            || substr($header, 4, 4) === 'ftyp'
            || str_starts_with($header, "II*\0")
            || str_starts_with($header, "MM\0*")
            || str_starts_with($header, 'BM');

        if (!$isRaster) {
            throw new InvalidArgumentException('The file is not a raster image.');
        }
    }

    public function transform(string $path, Transformation $transformation): ImageInterface
    {
        $width = $transformation->getWidth();
        $height = $transformation->getHeight();
        $isCrop = $width && $height && !$transformation->keepsAspectRatio();
        $scaleUp = $transformation->scalesUp();

        $source = $this->getJpegSize($path);
        $scale = $source ? $this->getScale($source[0], $width, $height, $isCrop) : null;

        // A large JPEG is decoded at the size needed, so it never takes the pixel cache of its full size (#694).
        $image = $source && $scale !== null && $scale <= 0.5
            ? $this->readJpegAtSize($path, $source, $scale)
            : $this->read($path);

        $isReduced = $source !== null && $image->width() < $source[0]->width();

        if ($isCrop) {
            $background = $this->getBackground($transformation);

            $image = match (true) {
                $background === null => $scaleUp || $isReduced ? $image->cover($width, $height) : $image->coverDown($width, $height),
                $scaleUp => $image->contain($width, $height, $background),
                default => $image->containDown($width, $height, $background),
            };
        } elseif ($isReduced) {
            // Sized by the original, as `Transformation::getSizeFor()` is: the reduced decode rounds its sides.
            $size = $source[0]->scaleDown($width, $height);
            $image = $image->resize($size->width(), $size->height());
        } elseif ($width || $height) {
            $image = $scaleUp ? $image->scale($width, $height) : $image->scaleDown($width, $height);
        }

        [$x, $y] = $transformation->getResolution();

        return $image->setResolution($x, $y);
    }

    /**
     * The upright size of a local JPEG, and whether its EXIF orientation turns it.
     *
     * @return array{Size, bool}|null
     */
    protected function getJpegSize(string $path): ?array
    {
        $path = (string)Yii::getAlias($path);

        if (!$this->isImagick() || !stream_is_local($path)) {
            return null;
        }

        try {
            $ping = new Imagick();
            $ping->pingImage($path);

            if ($ping->getImageFormat() !== 'JPEG') {
                return null;
            }

            $size = new Size($ping->getImageWidth(), $ping->getImageHeight());
            $isRotated = $ping->getImageOrientation() >= Imagick::ORIENTATION_LEFTTOP;
        } catch (ImagickException) {
            return null;
        }

        return [$isRotated ? new Size($size->height(), $size->width()) : $size, $isRotated];
    }

    /**
     * The factor the transformation scales the source by: what a crop covers, what anything else fits into.
     */
    protected function getScale(Size $source, ?int $width, ?int $height, bool $isCrop): ?float
    {
        $factors = array_filter([
            $width ? $width / $source->width() : null,
            $height ? $height / $source->height() : null,
        ]);

        if (!$factors) {
            return null;
        }

        return $isCrop ? max($factors) : min($factors);
    }

    /**
     * @param array{Size, bool} $source
     */
    protected function readJpegAtSize(string $path, array $source, float $scale): ImageInterface
    {
        [$size, $isRotated] = $source;
        $width = (int)ceil($size->width() * $scale);
        $height = (int)ceil($size->height() * $scale);

        $imagick = new Imagick();
        $imagick->setOption('jpeg:size', $isRotated ? "{$height}x$width" : "{$width}x$height");

        try {
            $imagick->readImage((string)Yii::getAlias($path));
        } catch (ImagickException $exception) {
            throw new ImageDecoderException("Failed to decode image data from file \"$path\": {$exception->getMessage()}", previous: $exception);
        }

        return $this->getManager()->decode($imagick);
    }

    /**
     * Reduces the image in place to fit `$maxSize` on its longest side, keeping its format, quality and metadata.
     *
     * @return array{int, int} the size written
     */
    public function reduce(string $path, string $extension, int $maxSize): array
    {
        $image = $this->transform($path, Transformation::make('reduce')->width($maxSize)->height($maxSize)->keepAspectRatio());
        $target = "$path.$extension";

        $this->write($image, $target);

        if (!rename($target, $path)) {
            @unlink($target);
            throw new InvalidArgumentException("Image \"$path\" could not be written.");
        }

        return [$image->width(), $image->height()];
    }

    /**
     * Whether the image library gave up for the host's resource limits rather than for the data: the image is too
     * large for this server, not broken.
     */
    public static function isResourceLimitError(Throwable|string $error): bool
    {
        if (is_string($error)) {
            return (bool)preg_match('/cache resources exhausted|exceeds limit|too large|memory allocation failed/i', $error);
        }

        for ($current = $error; $current !== null; $current = $current->getPrevious()) {
            if (self::isResourceLimitError($current->getMessage())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Encodes the image by the path's extension. A quality the options do not name is the source's, where the driver
     * knows it.
     *
     * @param array<string, mixed> $options `jpegQuality`, `webpQuality`, `avifQuality`, `stripMetadata`
     * @see Transformation::getImageOptions()
     */
    public function write(ImageInterface $image, string $path, array $options = []): void
    {
        $this->normalizeColorProfile($image);

        if ($options['stripMetadata'] ?? false) {
            $this->stripMetadata($image);
        }

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

        $encoded = $this->encode($image, $extension, is_int($quality) ? $quality : null);

        if ($encoded === '') {
            throw new InvalidArgumentException("Image \"$path\" could not be encoded: the image library has no \"$extension\" encoder.");
        }

        // Imagick's own writer does not support stream wrappers, and a wrapper need not support `rename()`
        if (!str_contains($path, '://')) {
            $this->replaceFile($path, $encoded);
        } elseif (file_put_contents($path, $encoded) === false) {
            throw new InvalidArgumentException("Image \"$path\" could not be written.");
        }
    }

    /**
     * A concurrent request for the same derivative may be serving the file, so it is replaced whole rather than
     * truncated and rewritten.
     */
    private function replaceFile(string $path, string $contents): void
    {
        // Named apart from the file: a basename near the column's length would push the name past the file system's
        $tempPath = dirname($path) . DIRECTORY_SEPARATOR . '.' . bin2hex(random_bytes(8)) . '.tmp';

        if (file_put_contents($tempPath, $contents) === false || !rename($tempPath, $path)) {
            @unlink($tempPath);
            throw new InvalidArgumentException("Image \"$path\" could not be written.");
        }
    }

    /**
     * Whether this server really writes the format. ImageMagick lists AVIF whenever it was built with libheif, which
     * then encodes nothing without its AV1 encoder plugin (#271), so the answer comes from encoding a pixel. That
     * takes milliseconds, hence the data cache.
     *
     * A failure is cached only while a JPEG still encodes: when that fails too, the library itself is broken (a
     * server holding an ImageMagick an upgrade replaced, #475), and a cached answer would outlast the repair.
     */
    public function canEncode(string $extension): bool
    {
        $extension = strtolower($extension);

        if (isset($this->encodable[$extension])) {
            return $this->encodable[$extension];
        }

        $cache = Yii::$app->getCache();
        $key = [static::class, $this->driver, $extension];
        $encodable = $cache?->get($key);

        if (!is_int($encodable)) {
            $encodable = (int)$this->probe($extension);

            if ($encodable || ($extension !== 'jpg' && $this->canEncode('jpg'))) {
                $cache?->set($key, $encodable, 3600);
            }
        }

        return $this->encodable[$extension] = (bool)$encodable;
    }

    protected function probe(string $extension): bool
    {
        try {
            return $this->encode($this->getManager()->createImage(1, 1), $extension) !== '';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Whether the driver writes the format at the quality it is given. ImageMagick 6 (Ubuntu 24.04) ignores it for
     * WebP whichever way it is set.
     */
    public function appliesQuality(string $extension): bool
    {
        $extension = strtolower($extension);

        if (!$this->isImagick()) {
            return true;
        }

        if (isset($this->qualityApplied[$extension])) {
            return $this->qualityApplied[$extension];
        }

        $cache = Yii::$app->getCache();
        $key = [static::class, $this->driver, $extension, 'quality'];
        $applied = $cache?->get($key);

        if (!is_int($applied)) {
            $applied = (int)$this->probeQuality($extension);
            $cache?->set($key, $applied, 3600);
        }

        return $this->qualityApplied[$extension] = (bool)$applied;
    }

    /**
     * GD stands in only for WebP, the one format ImageMagick 6 writes at its own quality.
     */
    public function canEncodeWithGd(string $extension): bool
    {
        return strtolower($extension) === 'webp' && function_exists('imagewebp');
    }

    public function getLibraryVersion(): string
    {
        if ($this->isImagick()) {
            preg_match('/ImageMagick \S+/', Imagick::getVersion()['versionString'], $matches);
            return $matches[0] ?? 'ImageMagick';
        }

        return 'GD ' . (gd_info()['GD Version'] ?? '');
    }

    protected function probeQuality(string $extension): bool
    {
        try {
            $rose = $this->getManager()->decodeBinary((new Imagick('rose:'))->getImageBlob());

            return strlen((string)$rose->encodeUsingFileExtension($extension, quality: 5))
                !== strlen((string)$rose->encodeUsingFileExtension($extension, quality: 95));
        } catch (Throwable) {
            return true;
        }
    }

    protected function encode(ImageInterface $image, string $extension, ?int $quality = null): string
    {
        $native = $image->core()->native();

        if (
            $quality !== null
            && $native instanceof Imagick
            && !$this->appliesQuality($extension)
            && $this->canEncodeWithGd($extension)
        ) {
            return $this->encodeWebpWithGd($image, $native, $quality);
        }

        return (string)($quality === null
            ? $image->encodeUsingFileExtension($extension)
            : $image->encodeUsingFileExtension($extension, quality: $quality));
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

    /**
     * Intervention's `strip` encoder option misses PNG, so the image is stripped before any encoder sees it. GD writes
     * no metadata at all.
     */
    protected function stripMetadata(ImageInterface $image): void
    {
        if ($image->core()->native() instanceof Imagick) {
            $image->modify(new StripMetaModifier());
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

    /**
     * GD writes neither a profile nor metadata, so what the image still holds is added to its container.
     */
    protected function encodeWebpWithGd(ImageInterface $image, Imagick $native, int $quality): string
    {
        $png = (string)$image->encodeUsingFileExtension('png');
        $webp = (string)ImageManager::usingDriver(GdDriver::class)
            ->decodeBinary($png)
            ->encodeUsingFileExtension('webp', quality: $quality);

        $profiles = array_filter($native->getImageProfiles('*'), is_string(...));

        return WebpContainer::addProfiles($webp, $profiles, $image->width(), $image->height());
    }

    protected function isImagick(): bool
    {
        return is_a($this->driver, Driver::class, true);
    }

    protected function getManager(): ImageManagerInterface
    {
        return $this->manager ??= ImageManager::usingDriver($this->driver);
    }
}
