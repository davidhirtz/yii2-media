<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Widgets;

use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Media\Widgets\Picture;
use Hirtz\Skeleton\Html\Img;
use Hirtz\Skeleton\Html\Source;

class PictureTest extends TestCase
{
    use MediaFixtureTrait;
    use ModuleTrait;

    protected function setUp(): void
    {
        parent::setUp();

        self::getModule()->transformations = [
            ...self::getModule()->transformations,
            'xs' => [
                'width' => 400,
            ],
            'md' => [
                'width' => 800,
            ],
            'xl' => [
                'width' => 1600,
            ],
        ];
    }

    public function testImage(): void
    {
        $file = $this->getFileFromFixture('file-2');

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $expected = Img::make()
            ->src($file->getUrl())
            ->alt($file->alt_text)
            ->loading('lazy');

        $actual = Picture::make()
            ->asset($asset)
            ->transformations(['md']);

        $this->assertEquals($expected->render(), $actual->render(true));

        $expected->class('lazyload');

        $this->assertEquals($expected->render(), $actual->image(fn (Img $img) => $img->addClass('lazyload'))
            ->render(true));

        $expected = \Hirtz\Skeleton\Html\Picture::make()
            ->content($expected);

        $this->assertEquals($expected->render(), $actual->omitUnnecessaryPictureTag(false)
            ->render(true));

        $expected->class('picture');

        $this->assertEquals($expected->render(), $actual->omitUnnecessaryPictureTag(true)
            ->picture(fn (\Hirtz\Skeleton\Html\Picture $picture) => $picture->addClass('picture'))
            ->render(true));
    }

    public function testSource(): void
    {
        $file = $this->getFileFromFixture('file-1');

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $picture = Picture::make()
            ->asset($asset)
            ->enableLegacyFileFormats(true)
            ->transformations(['xs']);

        $needle = Source::make()
            ->type('image/webp')
            ->src('/uploads/default/xs/test-1.webp')
            ->render();

        $this->assertStringContainsString($needle, $picture->render(true));

        $needle = Source::make()
            ->type('image/webp')
            ->sizes('100vw')
            ->srcset([400 => '/uploads/default/xs/test-1.webp', 800 => '/uploads/default/md/test-1.webp'])
            ->render();

        $haystack = $picture->sizes('100vw')
            ->transformations(['xs', 'md'])
            ->render(true);

        $this->assertStringContainsString($needle, $haystack);
    }
}
