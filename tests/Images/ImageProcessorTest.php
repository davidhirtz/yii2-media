<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Images;

use Hirtz\Media\Helpers\ImageSize;
use Hirtz\Media\Images\ImageProcessor;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\FileTransformation;
use Hirtz\Media\Test\Images\TestImageProcessor;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Skeleton\Helpers\FileHelper;
use Imagick;
use Intervention\Image\Drivers\Imagick\Driver;
use ImagickPixel;
use ImagickException;
use Intervention\Image\Exceptions\ImageDecoderException;
use Intervention\Image\Interfaces\ImageInterface;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Yii;
use yii\base\InvalidArgumentException;
use yii\caching\CacheInterface;

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

    public function testVectorMarkupNeverReachesImageMagick(): void
    {
        $filename = "$this->path/photo.jpg";
        file_put_contents($filename, '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>');

        $this->expectException(InvalidArgumentException::class);
        (new ImageProcessor())->read($filename);
    }

    /**
     * Intervention's own message names the file but not why ImageMagick refused it, a host's resource limit or a
     * damaged file.
     */
    public function testADecodeFailureSaysWhy(): void
    {
        $filename = "$this->path/damaged.jpg";
        file_put_contents($filename, "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 64));

        try {
            (new ImageProcessor())->read($filename);
            self::fail('The damaged file was decoded.');
        } catch (ImageDecoderException $exception) {
            self::assertStringStartsWith("Failed to decode image data from file \"$filename\": ", $exception->getMessage());
            self::assertInstanceOf(ImagickException::class, $exception->getPrevious());
        }
    }

    /**
     * A host's ImageMagick policy grants less pixel cache than a 50-megapixel photo takes, and refused it as "cache
     * resources exhausted" (#694): a JPEG is decoded at the size the transformation needs.
     */
    public function testALargeJpegIsTransformedWithinTheHostsLimits(): void
    {
        $filename = $this->writeSource(4000, 3000);
        $limits = [
            Imagick::RESOURCETYPE_MEMORY => 32_000_000,
            Imagick::RESOURCETYPE_MAP => 64_000_000,
            Imagick::RESOURCETYPE_DISK => 64_000_000,
        ];

        $previous = array_map(ImageProcessor::getResourceLimit(...), array_combine(array_keys($limits), array_keys($limits)));

        try {
            foreach ($limits as $type => $limit) {
                Imagick::setResourceLimit($type, $limit);
            }

            $image = $this->getProcessor()->transform($filename, Transformation::make('a')->width(300));
            self::assertSame([300, 225], [$image->width(), $image->height()]);

            $image = $this->getProcessor()->transform($filename, Transformation::make('a')->width(200)->height(200));
            self::assertSame([200, 200], [$image->width(), $image->height()]);
        } finally {
            foreach ($previous as $type => $limit) {
                Imagick::setResourceLimit($type, $limit);
            }
        }
    }

    /**
     * A sparse disk cache on a host out of temp space let a 50-megapixel upload be stored black.
     */
    public function testTheDiskPixelCacheIsAllocatedUpFront(): void
    {
        $previous = getenv('MAGICK_SYNCHRONIZE');

        try {
            putenv('MAGICK_SYNCHRONIZE');
            new ImageProcessor();
            self::assertSame('true', getenv('MAGICK_SYNCHRONIZE'));

            putenv('MAGICK_SYNCHRONIZE=false');
            new ImageProcessor();
            self::assertSame('false', getenv('MAGICK_SYNCHRONIZE'));
        } finally {
            putenv($previous === false ? 'MAGICK_SYNCHRONIZE' : "MAGICK_SYNCHRONIZE=$previous");
        }
    }

    /**
     * A host's `/tmp` can be a tmpfs smaller than the pixel cache of a large upload.
     */
    public function testTheModuleMovesThePixelCacheToItsTemporaryPath(): void
    {
        $module = File::getModule();
        $module->imageProcessor = ImageProcessor::class;
        $module->imageTemporaryPath = "$this->path/imagick";

        $previous = Imagick::getRegistry('temporary-path');

        try {
            $module->getImageProcessor();

            self::assertSame("$this->path/imagick", Imagick::getRegistry('temporary-path'));
            self::assertDirectoryExists("$this->path/imagick");
        } finally {
            // The registry outlives the test, and tearDown() removes the directory
            Imagick::setRegistry('temporary-path', $previous ?: sys_get_temp_dir());
        }
    }

    /**
     * ImageMagick 6.9.11 answers a progressive JPEG libjpeg had no memory for as a black image; a black one stands in.
     */
    public function testABlackLargeProgressiveJpegIsRefusedAsTooLarge(): void
    {
        $filename = $this->writeSource(2000, 2000, $this->blackProgressive(...));

        foreach ([fn () => $this->getProcessor()->read($filename), fn () => $this->getProcessor()->transform($filename, Transformation::make('a')->width(500))] as $decode) {
            try {
                $decode();
                self::fail('The black progressive JPEG was decoded.');
            } catch (ImageDecoderException $exception) {
                self::assertTrue(ImageProcessor::isResourceLimitError($exception));
            }
        }
    }

    public function testABlackSmallProgressiveJpegIsRead(): void
    {
        $image = $this->getProcessor()->read($this->writeSource(200, 100, $this->blackProgressive(...)));
        self::assertSame([200, 100], [$image->width(), $image->height()]);
    }

    public function testABlankDecodeIsTriedAgainWithThePixelCacheOnDisk(): void
    {
        $processor = new class () extends ImageProcessor {
            /**
             * @var list<int>
             */
            public array $diskResources = [];

            #[Override]
            protected function isBlankProgressiveJpeg(ImageInterface $image): bool
            {
                $this->diskResources[] = Imagick::getResource(Imagick::RESOURCETYPE_DISK);
                return count($this->diskResources) === 1;
            }
        };

        $memory = Imagick::getResourceLimit(Imagick::RESOURCETYPE_MEMORY);
        $image = $processor->read($this->writeSource(2000, 2000));

        self::assertSame([2000, 2000], [$image->width(), $image->height()]);
        self::assertSame(0, $processor->diskResources[0]);
        self::assertGreaterThan(0, $processor->diskResources[1]);
        self::assertSame($memory, Imagick::getResourceLimit(Imagick::RESOURCETYPE_MEMORY));
    }

    public function testAnExhaustedHostIsAResourceLimit(): void
    {
        self::assertTrue(ImageProcessor::isResourceLimitError("unable to extend cache `photo.jpg': No such file or directory"));
        self::assertTrue(ImageProcessor::isResourceLimitError("Insufficient memory (case 4) `photo.jpg'"));
        self::assertFalse(ImageProcessor::isResourceLimitError('Premature end of JPEG file'));
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
            // a red band on the left, well inside what a centred square crop cuts away (50px)
            $draw = new \ImagickDraw();
            $draw->setFillColor('red');
            $draw->rectangle(0, 0, 39, 299);
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

    public function testAQualityImageMagickIgnoresIsEncodedWithGd(): void
    {
        $processor = new TestImageProcessor();
        $processor->qualityIgnored = ['webp'];

        $image = new Imagick('rose:');
        $image->writeImage("jpeg:$this->path/rose.jpg");

        $processor->write($processor->read("$this->path/rose.jpg"), "$this->path/low.webp", ['webpQuality' => 5]);
        $processor->write($processor->read("$this->path/rose.jpg"), "$this->path/high.webp", ['webpQuality' => 95]);

        $webp = new Imagick("$this->path/low.webp");
        self::assertSame('WEBP', $webp->getImageFormat());
        self::assertSame([70, 46], [$webp->getImageWidth(), $webp->getImageHeight()]);
        self::assertLessThan(filesize("$this->path/high.webp"), filesize("$this->path/low.webp"));
    }

    public function testAWebpEncodedWithGdKeepsItsProfileAndMetadata(): void
    {
        $processor = new TestImageProcessor();
        $processor->qualityIgnored = ['webp'];

        $profile = $this->createProfile('RGB ');
        $filename = $this->writeSource(41, 31, function (Imagick $image) use ($profile): void {
            $image->setImageProfile('icc', $profile);
            $image->setImageProfile('xmp', '<x:xmpmeta xmlns:x="adobe:ns:meta/"/>');
        });

        $processor->write($processor->read($filename), "$this->path/profile.webp", ['webpQuality' => 50]);

        $webp = new Imagick("$this->path/profile.webp");
        self::assertSame([41, 31], [$webp->getImageWidth(), $webp->getImageHeight()]);
        self::assertSame($profile, $webp->getImageProfile('icc'));
        self::assertStringContainsString('xmpmeta', $webp->getImageProfile('xmp'));
    }

    public function testAWebpEncodedWithGdKeepsItsAlpha(): void
    {
        $processor = new TestImageProcessor();
        $processor->qualityIgnored = ['webp'];

        $image = new Imagick();
        $image->newImage(40, 30, new ImagickPixel('transparent'));
        $image->setImageFormat('png');
        $image->setImageProfile('icc', $this->createProfile('RGB '));
        $image->writeImage("$this->path/alpha.png");

        $processor->write($processor->read("$this->path/alpha.png"), "$this->path/alpha.webp", ['webpQuality' => 50]);

        $webp = new Imagick("$this->path/alpha.webp");
        self::assertTrue($webp->getImageAlphaChannel());
        self::assertSame(0.0, $webp->getImagePixelColor(5, 5)->getColorValue(Imagick::COLOR_ALPHA));
    }

    public function testTheQualityIsAppliedAndCached(): void
    {
        self::assertTrue($this->getProcessor()->appliesQuality('jpg'));
        self::assertSame(1, $this->getCache()->get([ImageProcessor::class, Driver::class, 'jpg', 'quality']));

        self::assertTrue((new ImageProcessor(\Intervention\Image\Drivers\Gd\Driver::class))->appliesQuality('webp'));
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

        self::assertSame([100, 200], [$image->width(), $image->height()]);

        $processor->write($image, "$this->path/upright.jpg");

        $written = new Imagick("$this->path/upright.jpg");
        self::assertSame([100, 200], [$written->getImageWidth(), $written->getImageHeight()]);
        self::assertContains($written->getImageOrientation(), [Imagick::ORIENTATION_UNDEFINED, Imagick::ORIENTATION_TOPLEFT]);
        self::assertSame(1, ImageSize::getOrientation("$this->path/upright.jpg"));
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

    public function testATransformationLosesItsMetadataButKeepsItsProfile(): void
    {
        $profile = $this->createProfile('RGB ');
        $file = $this->createFileWithMetadata($profile);

        foreach (['avif', 'webp', null] as $extension) {
            $written = new Imagick($this->createTransformation($file, $extension)->getFilePath());
            $profiles = $written->getImageProfiles();

            self::assertArrayNotHasKey('exif', $profiles, (string)$extension);
            self::assertArrayNotHasKey('xmp', $profiles, (string)$extension);
            self::assertSame([], $written->getImageProperties('exif:*'), (string)$extension);
            self::assertSame($profile, $profiles['icc'] ?? null, (string)$extension);
        }
    }

    public function testAPngLosesItsMetadata(): void
    {
        $processor = $this->getProcessor();
        $processor->write($processor->read($this->createFileWithMetadata()->getFilePath()), "$this->path/stripped.png", [
            'stripMetadata' => true,
        ]);

        $written = new Imagick("$this->path/stripped.png");

        self::assertArrayNotHasKey('exif', $written->getImageProfiles());
        self::assertSame([], $written->getImageProperties('exif:*'));
    }

    public function testATransformationKeepsItsMetadataWhenTheModuleSaysSo(): void
    {
        File::getModule()->stripMetadata = false;
        $file = $this->createFileWithMetadata();

        foreach (['avif', 'webp', null] as $extension) {
            $profiles = (new Imagick($this->createTransformation($file, $extension)->getFilePath()))->getImageProfiles();
            self::assertArrayHasKey('exif', $profiles, (string)$extension);
            self::assertArrayHasKey('xmp', $profiles, (string)$extension);
        }
    }

    public function testAnExistingFileIsReplacedRatherThanRewritten(): void
    {
        $processor = $this->getProcessor();
        $image = $processor->read($this->writeSource(40, 30));

        $processor->write($image, "$this->path/replaced.jpg");
        $inode = fileinode("$this->path/replaced.jpg");

        $processor->write($image, "$this->path/replaced.jpg");

        self::assertNotSame($inode, fileinode("$this->path/replaced.jpg"));
        self::assertSame([], glob("$this->path/*.tmp"));
    }

    /**
     * Imagick on a server without libheif's AV1 encoder answers an empty string for AVIF and says nothing (#271).
     */
    public function testAnEmptyEncodeIsRefusedAndWritesNothing(): void
    {
        $processor = new TestImageProcessor();
        $processor->unencodable = ['avif'];

        $filename = $this->writeSource(40, 30);

        try {
            $processor->write($processor->read($filename), "$this->path/empty.avif");
            self::fail('An empty encode must throw.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('"avif"', $exception->getMessage());
        }

        self::assertFileDoesNotExist("$this->path/empty.avif");
    }

    public function testCanEncodeAnswersWhatTheServerWrites(): void
    {
        $processor = new TestImageProcessor();
        $processor->unencodable = ['avif'];

        self::assertFalse($processor->canEncode('avif'));
        self::assertFalse($processor->canEncode('AVIF'));
        self::assertTrue($processor->canEncode('webp'));
        self::assertFalse($processor->canEncode('nonsense'));

        self::assertTrue($this->getProcessor()->canEncode('avif'));
    }

    public function testAFormatTheServerLacksIsCached(): void
    {
        $processor = new TestImageProcessor();
        $processor->unencodable = ['avif'];

        self::assertFalse($processor->canEncode('avif'));
        self::assertSame(0, $this->getCache()->get([TestImageProcessor::class, Driver::class, 'avif']));

        $processor = new TestImageProcessor();

        self::assertFalse($processor->canEncode('avif'));
    }

    /**
     * An ImageMagick an upgrade replaced under a running server encodes nothing (#475).
     */
    public function testABrokenLibraryIsNotCached(): void
    {
        $processor = new TestImageProcessor();
        $processor->unencodable = ['avif', 'jpg'];

        self::assertFalse($processor->canEncode('avif'));
        self::assertFalse($processor->canEncode('jpg'));
        self::assertFalse($this->getCache()->get([TestImageProcessor::class, Driver::class, 'avif']));
        self::assertFalse($this->getCache()->get([TestImageProcessor::class, Driver::class, 'jpg']));

        self::assertTrue((new TestImageProcessor())->canEncode('avif'));
    }

    public function testTheModuleCreatesTheProcessorFromItsConfiguration(): void
    {
        $module = File::getModule();

        $module->imageProcessor = [
            'class' => ImageProcessor::class,
            '__construct()' => [\Intervention\Image\Drivers\Gd\Driver::class],
        ];

        $processor = $module->getImageProcessor();

        self::assertSame($processor, $module->getImageProcessor());
        self::assertInstanceOf(\GdImage::class, $processor->read($this->writeSource(20, 10))->core()->native());
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

    private function createFileWithMetadata(?string $profile = null): File
    {
        $profile ??= $this->createProfile('RGB ');
        $file = $this->createFile('metadata', 'jpg', 400, 300);

        $jpeg = (string)file_get_contents($this->writeSource(400, 300, function (Imagick $image) use ($profile): void {
            $image->setImageProfile('icc', $profile);
            $image->setImageProfile('xmp', '<x:xmpmeta xmlns:x="adobe:ns:meta/"/>');
        }));

        file_put_contents($file->getFilePath(), $this->withExifOrientation($jpeg, 1));

        return $file;
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
        return File::getModule()->getImageProcessor();
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

    private function blackProgressive(Imagick $image): void
    {
        $image->negateImage(false);
        $image->setInterlaceScheme(Imagick::INTERLACE_JPEG);
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

    private function getCache(): CacheInterface
    {
        return Yii::$app->getCache() ?? self::fail('The test application has no cache.');
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
