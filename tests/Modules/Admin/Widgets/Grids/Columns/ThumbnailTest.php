<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Grids\Columns;

use Hirtz\Media\Modules\Admin\Widgets\Grids\Columns\Thumbnail;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Media\Test\Images\TestImageProcessor;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Media\Transformations\Transformation;

class ThumbnailTest extends TestCase
{
    use MediaFixtureTrait;
    use ModuleTrait;

    public function testTheThumbnailPrefersAvif(): void
    {
        self::getModule()->transformationExtensions = ['webp', 'avif'];
        $file = $this->getFileFromFixture('file-1');

        $expected = $file->getTransformationUrl(Transformation::NAME_ADMIN, 'avif');
        self::assertStringContainsString("src=\"$expected\"", (string)Thumbnail::make()->file($file));
    }

    public function testTheThumbnailFallsBackToTheNextEncodableExtension(): void
    {
        $processor = new TestImageProcessor();
        $processor->unencodable = ['avif'];
        self::getModule()->imageProcessor = $processor;

        $file = $this->getFileFromFixture('file-1');

        $expected = $file->getTransformationUrl(Transformation::NAME_ADMIN, 'webp');
        self::assertStringContainsString("src=\"$expected\"", (string)Thumbnail::make()->file($file));
    }

    public function testTheThumbnailKeepsTheFileExtensionWithoutAnEncodableExtension(): void
    {
        $processor = new TestImageProcessor();
        $processor->unencodable = ['avif', 'webp'];
        self::getModule()->imageProcessor = $processor;

        $file = $this->getFileFromFixture('file-1');

        $expected = $file->getTransformationUrl(Transformation::NAME_ADMIN);
        self::assertStringContainsString("src=\"$expected\"", (string)Thumbnail::make()->file($file));
    }
}
