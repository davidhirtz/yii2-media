<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Widgets;

use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Media\Widgets\Media;
use Hirtz\Skeleton\Html\Img;
use Hirtz\Skeleton\Html\Picture;
use Hirtz\Skeleton\Html\Source;

class MediaTest extends TestCase
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
        $file = $this->getFileFromFixture('file-1');

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $expected = Img::make()
            ->addStyle(['aspect-ratio' => '1'])
            ->srcset($file->getSrcset(['xs', 'md'], 'avif'));

        $actual = Media::make()
            ->asset($asset)
            ->aspectRatio(true)
            ->image(fn (Img $img) => $img->alt(''))
            ->lazyLoading(false)
            ->transformations(['xs', 'md']);

        $this->assertEquals($expected->render(), $actual->render(true));

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $file = $this->getFileFromFixture('file-2');

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $expected = Img::make()
            ->src($file->getUrl())
            ->alt($file->alt_text);

        $actual = Media::make()
            ->asset($asset)
            ->lazyLoading(false)
            ->transformations(['md']);

        $this->assertEquals($expected->render(), $actual->render(true));

        $expected->class('lazyload')
            ->loading('lazy');

        $this->assertEquals($expected->render(), $actual->image(fn (Img $img) => $img->addClass('lazyload'))
            ->lazyLoading()
            ->render(true));

        $expected = Picture::make()
            ->content($expected);

        $this->assertEquals($expected->render(), $actual->omitUnnecessaryPictureTag(false)
            ->render(true));

        $expected->class('picture');

        $this->assertEquals($expected->render(), $actual->omitUnnecessaryPictureTag(true)
            ->picture(fn (Picture $picture) => $picture->addClass('picture'))
            ->render(true));
    }

    public function testLoadingAndFetchPriorityFromAsset(): void
    {
        $file = $this->getFileFromFixture('file-2');

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $media = Media::make()
            ->asset($asset)
            ->lazyLoading()
            ->transformations(['md']);

        self::assertStringContainsString('loading="lazy"', $media->render(true));
        self::assertStringNotContainsString('fetchpriority', $media->render(true));

        $asset->loading = 'eager';
        $asset->fetchpriority = 'high';

        $html = $media->render(true);

        self::assertStringContainsString('loading="eager"', $html);
        self::assertStringContainsString('fetchpriority="high"', $html);
    }

    public function testSource(): void
    {
        $file = $this->getFileFromFixture('file-1');

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $picture = Media::make()
            ->asset($asset)
            ->extension(null)
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
