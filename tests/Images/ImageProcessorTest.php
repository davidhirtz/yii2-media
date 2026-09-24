<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Images;

use Hirtz\Media\Images\ImageProcessor;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\FileTransformation;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Skeleton\Helpers\FileHelper;
use Imagick;
use ImagickPixel;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Yii;

/**
 * Every image is generated here: a committed binary, or a profile only one system ships, would not be portable.
 */
class ImageProcessorTest extends TestCase
{
    use MediaFileTrait;

    private string $path;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->logger->isRecording = true;

        $this->path = Yii::getAlias('@runtime/image-processor-test');
        FileHelper::createDirectory($this->path);

        $this->folder = $this->createFolder('Uploads', 'uploads');
    }

    #[Override]
    protected function tearDown(): void
    {
        if (in_array(ProxyStreamWrapper::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(ProxyStreamWrapper::PROTOCOL);
        }

        FileHelper::removeDirectory($this->path);
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);

        parent::tearDown();
    }

    public function testAnImageIsScaledDown(): void
    {
        $image = $this->transform(Transformation::make('a')->width(100), 400, 300);

        self::assertSame([100, 75], [$image->width(), $image->height()]);
    }

    public function testAnImageIsNotScaledUp(): void
    {
        $image = $this->transform(Transformation::make('a')->width(800), 400, 300);

        self::assertSame([400, 300], [$image->width(), $image->height()]);
    }

    public function testAnImageIsScaledUpWhenAsked(): void
    {
        $image = $this->transform(Transformation::make('a')->width(800)->scaleUp(), 400, 300);

        self::assertSame([800, 600], [$image->width(), $image->height()]);
    }

    public function testAnImageIsCroppedToFill(): void
    {
        $filename = $this->writeSource(400, 300, function (Imagick $image): void {
            // a red band on the left, which a centred square crop cuts away
            $draw = new \ImagickDraw();
            $draw->setFillColor('red');
            $draw->rectangle(0, 0, 49, 299);
            $image->drawImage($draw);
        });

        $image = $this->getProcessor()->transform($filename, Transformation::make('a')->width(100)->height(100));

        self::assertSame([100, 100], [$image->width(), $image->height()]);
        // white, not red, give or take the JPEG compression: the green channel of `rrggbb`
        self::assertGreaterThan(240, hexdec(substr($image->colorAt(0, 50)->toHex(), 2, 2)));
    }

    public function testACropLargerThanTheImageKeepsItsAspectRatio(): void
    {
        $image = $this->transform(Transformation::make('a')->width(600)->height(200), 400, 300);
        self::assertSame([400, 133], [$image->width(), $image->height()]);

        $image = $this->transform(Transformation::make('a')->width(600)->height(200)->scaleUp(), 400, 300);
        self::assertSame([600, 200], [$image->width(), $image->height()]);
    }

    public function testALetterboxIsFilledWithTheBackgroundAndItsAlpha(): void
    {
        $transformation = Transformation::make('a')
            ->width(200)
            ->height(200)
            ->backgroundColor('#f00')
            ->backgroundAlpha(0);

        $filename = $this->writeSource(400, 200);
        $image = $this->getProcessor()->transform($filename, $transformation);
        $this->getProcessor()->write($image, "$this->path/letterbox.png");

        $written = new Imagick("$this->path/letterbox.png");
        self::assertSame([200, 200], [$written->getImageWidth(), $written->getImageHeight()]);
        self::assertSame(0.0, $written->getImagePixelColor(100, 10)->getColorValue(Imagick::COLOR_ALPHA));
        self::assertSame(1.0, $written->getImagePixelColor(100, 100)->getColorValue(Imagick::COLOR_ALPHA));

        $transformation->backgroundAlpha(100);
        $image = $this->getProcessor()->transform($filename, $transformation);

        self::assertSame('ff0000', $image->colorAt(100, 10)->toHex());
        self::assertSame(255, $image->colorAt(100, 10)->alpha()->value());
    }

    public function testAnIntegerBackgroundIsAColor(): void
    {
        $transformation = Transformation::make('a')->width(200)->height(200)->backgroundColor(0x00ff00);
        $image = $this->getProcessor()->transform($this->writeSource(400, 200), $transformation);

        self::assertSame('00ff00', $image->colorAt(100, 10)->toHex());
    }

    public function testTheResolutionIsWritten(): void
    {
        $image = $this->transform(Transformation::make('a')->width(100)->resolution(300), 400, 300);
        $this->getProcessor()->write($image, "$this->path/resolution.jpg");

        $resolution = (new Imagick("$this->path/resolution.jpg"))->getImageResolution();
        self::assertEqualsWithDelta(300, $resolution['x'], 1);
        self::assertEqualsWithDelta(300, $resolution['y'], 1);
    }

    public function testTheQualityOptionIsUsed(): void
    {
        $image = $this->transform(Transformation::make('a')->width(100), 400, 300);

        $this->getProcessor()->write($image, "$this->path/low.jpg", ['jpegQuality' => 20]);
        $this->getProcessor()->write($image, "$this->path/high.jpg", ['jpegQuality' => 95]);

        self::assertSame(20, (new Imagick("$this->path/low.jpg"))->getImageCompressionQuality());
        self::assertSame(95, (new Imagick("$this->path/high.jpg"))->getImageCompressionQuality());
    }

    public function testTheSourceQualityIsKeptWithoutAnOption(): void
    {
        $filename = $this->writeSource(40, 30, quality: 92);
        $this->getProcessor()->write($this->getProcessor()->read($filename), "$this->path/copy.jpg");

        self::assertSame(92, (new Imagick("$this->path/copy.jpg"))->getImageCompressionQuality());
    }

    public function testAFileIsRotatedClockwise(): void
    {
        $file = $this->createFile('rotate', 'png', 40, 20);
        $this->writeMarkedImage($file->getFilePath(), 40, 20);

        $file->angle = 90;
        self::assertSame(1, $file->update(), print_r($file->getErrors(), true));

        self::assertSame([20, 40], [$file->width, $file->height]);

        // the mark in the top left corner turns into the top right one
        $image = new Imagick($file->getFilePath());
        self::assertSame(['r' => 255, 'g' => 0, 'b' => 0], $this->getRgb($image, 18, 1));
        self::assertSame(['r' => 255, 'g' => 255, 'b' => 255], $this->getRgb($image, 1, 1));
    }

    public function testAFileIsCroppedAtItsPosition(): void
    {
        $file = $this->createFile('crop', 'png', 40, 20);
        $this->writeMarkedImage($file->getFilePath(), 40, 20);

        $file->width = 10;
        $file->height = 10;
        $file->x = 2;
        $file->y = 2;

        self::assertSame(1, $file->update(), print_r($file->getErrors(), true));

        $image = new Imagick($file->getFilePath());
        self::assertSame([10, 10], [$image->getImageWidth(), $image->getImageHeight()]);
        self::assertSame(['r' => 255, 'g' => 0, 'b' => 0], $this->getRgb($image, 0, 0));
        self::assertSame(['r' => 255, 'g' => 255, 'b' => 255], $this->getRgb($image, 5, 5));
    }

    public function testAnImageIsOrientedByItsExifData(): void
    {
        $filename = "$this->path/oriented.jpg";
        file_put_contents($filename, $this->withExifOrientation((string)file_get_contents($this->writeSource(200, 100)), 6));

        $processor = $this->getProcessor();
        $image = $processor->read($filename);

        self::assertTrue($processor->wasOriented($image));
        self::assertSame([100, 200], [$image->width(), $image->height()]);

        $processor->write($image, "$this->path/upright.jpg");

        $written = new Imagick("$this->path/upright.jpg");
        self::assertSame([100, 200], [$written->getImageWidth(), $written->getImageHeight()]);
        self::assertContains($written->getImageOrientation(), [Imagick::ORIENTATION_UNDEFINED, Imagick::ORIENTATION_TOPLEFT]);
        self::assertFalse($processor->wasOriented($processor->read("$this->path/upright.jpg")));
    }

    public function testAnUprightImageIsNotOriented(): void
    {
        $processor = $this->getProcessor();
        self::assertFalse($processor->wasOriented($processor->read($this->writeSource(20, 10))));
    }

    public function testAStreamIsReadAndWritten(): void
    {
        stream_wrapper_register(ProxyStreamWrapper::PROTOCOL, ProxyStreamWrapper::class, STREAM_IS_URL);

        $source = ProxyStreamWrapper::PROTOCOL . '://' . $this->writeSource(40, 30);
        $target = ProxyStreamWrapper::PROTOCOL . "://$this->path/stream.webp";

        self::assertFalse(stream_is_local($source));

        $processor = $this->getProcessor();
        $processor->write($processor->transform($source, Transformation::make('a')->width(20)), $target);

        self::assertSame([20, 15], array_slice((array)getimagesize("$this->path/stream.webp"), 0, 2));
    }

    public function testAnUnreadableStreamThrows(): void
    {
        stream_wrapper_register(ProxyStreamWrapper::PROTOCOL, ProxyStreamWrapper::class, STREAM_IS_URL);

        $this->expectException(\yii\base\InvalidArgumentException::class);
        $this->getProcessor()->read(ProxyStreamWrapper::PROTOCOL . "://$this->path/missing.jpg");
    }

    /**
     * @see https://github.com/davidhirtz/yii2-monorepo/issues/259
     */
    public function testAGreyJpegWithAGrayProfileLosesItInEveryFormat(): void
    {
        $file = $this->createFile('grey', 'jpg', 400, 300);

        $image = new Imagick();
        $image->newPseudoImage(400, 300, 'gradient:white-black');
        $image->transformImageColorspace(Imagick::COLORSPACE_GRAY);
        $image->setImageFormat('jpeg');
        $image->setImageProfile('icc', $this->createProfile('GRAY'));
        $image->writeImage($file->getFilePath());

        self::assertSame('GRAY', $this->getProfileSpace($file->getFilePath()));

        foreach (['avif', 'webp', null] as $extension) {
            $transformation = $this->createTransformation($file, $extension);
            self::assertNull($this->getProfileSpace($transformation->getFilePath()), (string)$extension);
        }
    }

    public function testAGreyPngWithTransparencyAndAGrayProfileLosesIt(): void
    {
        $file = $this->createFile('grey', 'png', 200, 100);

        $image = new Imagick();
        $image->newPseudoImage(200, 100, 'gradient:gray20-transparent');
        $image->transformImageColorspace(Imagick::COLORSPACE_GRAY);
        $image->setImageFormat('png');
        $image->setImageProfile('icc', $this->createProfile('GRAY'));
        $image->writeImage($file->getFilePath());

        self::assertSame('GRAY', $this->getProfileSpace($file->getFilePath()));

        $transformation = $this->createTransformation($file, 'avif');
        $written = new Imagick($transformation->getFilePath());

        self::assertNull($this->getProfileSpace($transformation->getFilePath()));
        self::assertTrue($written->getImageAlphaChannel());
    }

    public function testACmykJpegIsConvertedToRgb(): void
    {
        $file = $this->createFile('cmyk', 'jpg', 200, 100);

        $image = new Imagick();
        $image->newPseudoImage(200, 100, 'xc:red');
        $image->transformImageColorspace(Imagick::COLORSPACE_CMYK);
        $image->setImageFormat('jpeg');
        $image->setImageProfile('icc', $this->createProfile('CMYK'));
        $image->writeImage($file->getFilePath());

        $transformation = $this->createTransformation($file);
        $written = new Imagick($transformation->getFilePath());

        self::assertSame(Imagick::COLORSPACE_SRGB, $written->getImageColorspace());
        self::assertNull($this->getProfileSpace($transformation->getFilePath()));

        $red = $this->getRgb($written, 10, 10);
        self::assertGreaterThan(200, $red['r']);
        self::assertLessThan(50, $red['g']);
    }

    public function testAnRgbProfileIsKept(): void
    {
        $profile = $this->createProfile('RGB ');
        $filename = $this->writeSource(40, 30, fn (Imagick $image) => $image->setImageProfile('icc', $profile));

        $processor = $this->getProcessor();
        $processor->write($processor->read($filename), "$this->path/rgb.avif");

        self::assertSame($profile, (new Imagick("$this->path/rgb.avif"))->getImageProfile('icc'));
    }

    /**
     * @return array<string, array{Transformation, int, int}>
     */
    public static function transformationProvider(): array
    {
        return [
            'width' => [Transformation::make('a')->width(333), 1600, 1200],
            'height' => [Transformation::make('a')->height(251), 1600, 1200],
            'width, odd source' => [Transformation::make('a')->width(100), 333, 251],
            'height, odd source' => [Transformation::make('a')->height(100), 333, 251],
            'width, larger' => [Transformation::make('a')->width(500), 333, 251],
            'width, scaled up' => [Transformation::make('a')->width(500)->scaleUp(), 333, 251],
            'box' => [Transformation::make('a')->width(200)->height(200)->keepAspectRatio(), 333, 251],
            'box, portrait' => [Transformation::make('a')->width(200)->height(200)->keepAspectRatio(), 251, 333],
            'box, wide' => [Transformation::make('a')->width(1200)->height(630)->keepAspectRatio(), 1600, 1200],
            'box, one side larger' => [Transformation::make('a')->width(400)->height(100)->keepAspectRatio(), 333, 251],
            'box, larger' => [Transformation::make('a')->width(400)->height(400)->keepAspectRatio(), 333, 251],
            'box, scaled up' => [Transformation::make('a')->width(400)->height(400)->keepAspectRatio()->scaleUp(), 333, 251],
            'crop' => [Transformation::make('a')->width(100)->height(100), 333, 251],
            'crop, wide' => [Transformation::make('a')->width(300)->height(100), 333, 251],
            'crop, one side larger' => [Transformation::make('a')->width(500)->height(100), 333, 251],
            'crop, larger' => [Transformation::make('a')->width(500)->height(400), 333, 251],
            'crop, scaled up' => [Transformation::make('a')->width(500)->height(400)->scaleUp(), 333, 251],
            'letterbox' => [Transformation::make('a')->width(100)->height(100)->backgroundColor('fff'), 333, 251],
            'letterbox, larger' => [Transformation::make('a')->width(500)->height(400)->backgroundColor('fff'), 333, 251],
        ];
    }

    #[DataProvider('transformationProvider')]
    public function testTheSizeForAFileIsTheSizeWritten(Transformation $transformation, int $width, int $height): void
    {
        $image = $this->transform($transformation, $width, $height);
        $this->getProcessor()->write($image, "$this->path/size.jpg");

        $size = getimagesize("$this->path/size.jpg");
        self::assertNotFalse($size);

        $file = $this->buildFile('size', width: $width, height: $height);
        self::assertSame([$size[0], $size[1]], $transformation->getSizeFor($file));
    }

    private function transform(Transformation $transformation, int $width, int $height): \Intervention\Image\Interfaces\ImageInterface
    {
        return $this->getProcessor()->transform($this->writeSource($width, $height), $transformation);
    }

    private function createTransformation(File $file, ?string $extension = null): FileTransformation
    {
        File::getModule()->addTransformation(Transformation::make('small')->width(100)->keepAspectRatio());

        $transformation = FileTransformation::create();
        $transformation->name = 'small';
        $transformation->extension = $extension;
        $transformation->populateFileRelation($file);

        // a failed transformation is logged rather than added to the model, so the log is what says why
        self::assertTrue($transformation->insert(), $this->getLoggedErrors());

        return $transformation;
    }

    private function getLoggedErrors(): string
    {
        return implode("\n", array_map(
            fn (array $message): string => (string)$message[0],
            array_filter(
                $this->logger->messages,
                fn (array $m): bool => is_string($m[0]) && $m[1] === \yii\log\Logger::LEVEL_ERROR
            )
        ));
    }

    private function getProcessor(): ImageProcessor
    {
        return Yii::$container->get(ImageProcessor::class);
    }

    /**
     * @param (callable(Imagick): mixed)|null $callback
     */
    private function writeSource(int $width, int $height, ?callable $callback = null, int $quality = 90): string
    {
        $filename = "$this->path/source-{$width}x$height.jpg";

        $image = new Imagick();
        $image->newImage($width, $height, new ImagickPixel('white'));
        $image->setImageFormat('jpeg');
        $image->setImageCompressionQuality($quality);

        if ($callback) {
            $callback($image);
        }

        $image->writeImage($filename);

        return $filename;
    }

    /**
     * A white image with a red 4×4 mark in its top left corner.
     */
    private function writeMarkedImage(string $filename, int $width, int $height): void
    {
        $image = new Imagick();
        $image->newImage($width, $height, new ImagickPixel('white'));

        $draw = new \ImagickDraw();
        $draw->setFillColor('red');
        $draw->rectangle(0, 0, 3, 3);

        $image->drawImage($draw);
        $image->setImageFormat('png');
        $image->writeImage($filename);
    }

    /**
     * @return array{r: int, g: int, b: int}
     */
    private function getRgb(Imagick $image, int $x, int $y): array
    {
        $color = $image->getImagePixelColor($x, $y)->getColor();
        return ['r' => (int)$color['r'], 'g' => (int)$color['g'], 'b' => (int)$color['b']];
    }

    private function getProfileSpace(string $filename): ?string
    {
        $profiles = (new Imagick($filename))->getImageProfiles('icc');
        return isset($profiles['icc']) ? substr((string)$profiles['icc'], 16, 4) : null;
    }

    /**
     * A minimal ICC v2 display profile of the given color space: a description, a white point, a gamma curve and a
     * copyright. Only the header's color space matters to the processor.
     */
    private function createProfile(string $colorSpace): string
    {
        $description = 'Test profile';
        $d50 = pack('NNN', 0xF6D6, 0x10000, 0xD32D);

        $tags = [
            'desc' => 'desc' . "\0\0\0\0" . pack('N', strlen($description) + 1) . "$description\0"
                . pack('NNnC', 0, 0, 0, 0) . str_repeat("\0", 67),
            'wtpt' => 'XYZ ' . "\0\0\0\0" . $d50,
            'kTRC' => 'curv' . "\0\0\0\0" . pack('Nn', 1, 0x0233),
            'cprt' => 'text' . "\0\0\0\0" . "No copyright\0",
        ];

        $offset = 128 + 4 + 12 * count($tags);
        $table = pack('N', count($tags));
        $data = '';

        foreach ($tags as $signature => $tag) {
            $table .= $signature . pack('NN', $offset + strlen($data), strlen($tag));
            $data .= str_pad($tag, (int)ceil(strlen($tag) / 4) * 4, "\0");
        }

        $header = pack('N', $offset + strlen($data)) . "\0\0\0\0" . pack('N', 0x02100000) . 'mntr' . $colorSpace
            . 'XYZ ' . str_repeat("\0", 12) . 'acsp' . str_repeat("\0", 28) . $d50 . str_repeat("\0", 48);

        return $header . $table . $data;
    }

    /**
     * Inserts an APP1 segment holding nothing but the EXIF orientation after the JPEG's start of image marker.
     */
    private function withExifOrientation(string $jpeg, int $orientation): string
    {
        $tiff = "II*\0" . pack('V', 8) . pack('v', 1) . pack('vvVvv', 0x0112, 3, 1, $orientation, 0) . pack('V', 0);
        $payload = "Exif\0\0$tiff";

        return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload . substr($jpeg, 2);
    }
}

/**
 * Serves `image-processor-test://<absolute path>` from the file system, registered as a URL so the processor treats
 * it the way it treats S3.
 */
class ProxyStreamWrapper
{
    public const string PROTOCOL = 'image-processor-test';

    /** @var resource|null */
    public $context;

    /** @var resource|null */
    private $handle = null;

    public function stream_open(string $path, string $mode): bool
    {
        $handle = @fopen($this->getPath($path), $mode);

        if ($handle === false) {
            return false;
        }

        $this->handle = $handle;
        return true;
    }

    public function stream_read(int $count): string|false
    {
        return $this->handle ? fread($this->handle, max(1, $count)) : false;
    }

    public function stream_write(string $data): int
    {
        return $this->handle ? (int)fwrite($this->handle, $data) : 0;
    }

    public function stream_eof(): bool
    {
        return !$this->handle || feof($this->handle);
    }

    public function stream_seek(int $offset, int $whence): bool
    {
        return $this->handle && fseek($this->handle, $offset, $whence) === 0;
    }

    public function stream_tell(): int
    {
        return $this->handle ? (int)ftell($this->handle) : 0;
    }

    /**
     * @return array<int|string, int>|false
     */
    public function stream_stat(): array|false
    {
        return $this->handle ? fstat($this->handle) : false;
    }

    /**
     * @return array<int|string, int>|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        return @stat($this->getPath($path));
    }

    public function stream_close(): void
    {
        if ($this->handle) {
            fclose($this->handle);
        }
    }

    private function getPath(string $path): string
    {
        return substr($path, strlen(self::PROTOCOL) + 3);
    }
}
