<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Helpers;

use Hirtz\Media\Helpers\ImageSize;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Skeleton\Helpers\FileHelper;
use Override;
use Yii;

class ImageSizeTest extends TestCase
{
    use MediaFileTrait;

    private string $path;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->path = Yii::getAlias('@runtime/image-size-test');
        FileHelper::createDirectory($this->path);
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory($this->path);
        parent::tearDown();
    }

    public function testARasterImageAnswersItsSize(): void
    {
        $filename = "$this->path/image.png";
        imagepng(imagecreatetruecolor(30, 20), $filename);

        self::assertSame([30, 20], ImageSize::fromFile($filename));
    }

    public function testAnImageTurnedByItsExifOrientationAnswersTheSizeItIsDisplayedAt(): void
    {
        $filename = "$this->path/sideways.jpg";

        $this->writeImage($filename, 200, 100, 6);
        self::assertSame(6, ImageSize::getOrientation($filename));
        self::assertSame([100, 200], ImageSize::fromFile($filename));

        $this->writeImage($filename, 200, 100, 3);
        self::assertSame(3, ImageSize::getOrientation($filename));
        self::assertSame([200, 100], ImageSize::fromFile($filename));
    }

    public function testAnImageWithoutExifIsTopLeft(): void
    {
        $filename = "$this->path/image.png";
        imagepng(imagecreatetruecolor(30, 20), $filename);

        self::assertSame(1, ImageSize::getOrientation($filename));
        self::assertSame(1, ImageSize::getOrientation("$this->path/missing.jpg"));
    }

    public function testTheExtensionDecidesOverTheFilename(): void
    {
        $filename = "$this->path/upload.tmp";
        file_put_contents($filename, '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="8"/>');

        self::assertSame([12, 8], ImageSize::fromFile($filename, 'SVG'));
    }

    public function testAnSvgAnswersItsWidthAndHeightAttributes(): void
    {
        self::assertSame([120, 80], $this->getSvgSize('width="120" height="80" viewBox="0 0 12 8"'));
        self::assertSame([120, 80], $this->getSvgSize('width="120.5px" height="80px"'));
    }

    public function testAnSvgWithoutDimensionsAnswersItsViewBox(): void
    {
        self::assertSame([800, 600], $this->getSvgSize('viewBox="0 0 800 600"'));
        self::assertSame([800, 600], $this->getSvgSize('viewBox="0,0,800,600"'));
        self::assertSame([800, 600], $this->getSvgSize('viewBox="-10, -10  800.5 600"'));
    }

    public function testAnSvgMeasuredInOtherUnitsFallsBackToItsViewBox(): void
    {
        self::assertSame([800, 600], $this->getSvgSize('width="10cm" height="7.5cm" viewBox="0 0 800 600"'));
        self::assertSame([800, 600], $this->getSvgSize('width="100%" height="100%" viewBox="0 0 800 600"'));
        self::assertNull($this->getSvgSize('width="10cm" height="7.5cm"'));
    }

    public function testAnSvgWithoutAnySizeAnswersNull(): void
    {
        self::assertNull($this->getSvgSize(''));
    }

    public function testAFileThatIsNoSvgAnswersNull(): void
    {
        $filename = "$this->path/image.svg";

        file_put_contents($filename, 'not xml');
        self::assertNull(ImageSize::fromFile($filename));

        file_put_contents($filename, '<html width="10" height="10"/>');
        self::assertNull(ImageSize::fromFile($filename));
    }

    public function testAMissingFileAnswersNull(): void
    {
        self::assertNull(ImageSize::fromFile("$this->path/missing.svg"));
        self::assertNull(ImageSize::fromFile("$this->path/missing.jpg"));
    }

    /**
     * @return array{int, int}|null
     */
    private function getSvgSize(string $attributes): ?array
    {
        $filename = "$this->path/image.svg";
        file_put_contents($filename, "<svg xmlns=\"http://www.w3.org/2000/svg\" $attributes></svg>");

        return ImageSize::fromSvg($filename);
    }
}
