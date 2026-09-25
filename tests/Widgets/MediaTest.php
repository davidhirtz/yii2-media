<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Widgets;

use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Media\Test\Images\TestImageProcessor;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Media\Transformations\Transformation;
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

        $module = self::getModule();
        $module->addTransformation(Transformation::make('xs')->width(400));
        $module->addTransformation(Transformation::make('md')->width(800));
        $module->addTransformation(Transformation::make('xl')->width(1600));
    }

    /**
     * A server that lists AVIF but cannot write it serves the next format, rather than images that fail (#271).
     */
    public function testAFormatTheServerCannotWriteFallsBackToTheNext(): void
    {
        $processor = new TestImageProcessor();
        $processor->unencodable = ['avif'];
        self::getModule()->imageProcessor = $processor;

        self::assertSame(['webp'], self::getModule()->getTransformationExtensions());

        $file = $this->getFileFromFixture('file-1');

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $expected = Img::make()
            ->addStyle(['aspect-ratio' => '1'])
            ->srcset($file->getSrcset(['xs', 'md'], 'webp'));

        $actual = Media::make()
            ->asset($asset)
            ->aspectRatio(true)
            ->image(fn (Img $img) => $img->alt(''))
            ->lazyLoading(false)
            ->transformations(['xs', 'md']);

        $this->assertEquals($expected->render(), $actual->render(true));

        $picture = Media::make()
            ->asset($asset)
            ->omitUnnecessaryPictureTag(false)
            ->transformations(['xs', 'md'])
            ->render(true);

        self::assertStringContainsString('type="image/webp"', $picture);
        self::assertStringNotContainsString('avif', $picture);
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

    public function testTheClosuresStack(): void
    {
        $asset = TestAsset::create();
        $asset->populateFileRelation($this->getFileFromFixture('file-1'));

        $html = (string)Media::make()
            ->asset($asset)
            ->omitUnnecessaryPictureTag(false)
            ->image(fn (Img $img) => $img->addClass('first'))
            ->image(fn (Img $img) => $img->addClass('second'))
            ->picture(fn (Picture $picture) => $picture->addClass('first'))
            ->picture(fn (Picture $picture) => $picture->addClass('second'));

        self::assertStringContainsString('<picture class="first second">', $html);
        self::assertStringContainsString('<img class="first second"', $html);
    }

    /**
     * @see https://github.com/davidhirtz/yii2-monorepo/issues/269
     */
    public function testAPictureTagOffersEveryTransformationExtension(): void
    {
        $file = $this->getFileFromFixture('file-1');

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $expected = Picture::make()
            ->content(
                Source::make()
                    ->srcset($file->getSrcset(['xs', 'md'], 'avif'))
                    ->type('image/avif'),
                Source::make()
                    ->srcset($file->getSrcset(['xs', 'md'], 'webp'))
                    ->type('image/webp'),
                Img::make()
                    ->srcset($file->getSrcset(['xs', 'md']))
                    ->alt($file->alt_text),
            );

        $media = Media::make()
            ->asset($asset)
            ->lazyLoading(false)
            ->omitUnnecessaryPictureTag(false)
            ->transformations(['xs', 'md']);

        $html = $media->render(true);

        self::assertEquals($expected->render(), $html);
        self::assertStringContainsString('/uploads/default/md/test-1.avif', $html);
        self::assertStringContainsString('/uploads/default/md/test-1.webp', $html);
        self::assertStringContainsString('/uploads/default/md/test-1.jpg', $html);

        // `extension(null)` names the fallback's format already, the sources are the same
        self::assertEquals($expected->render(), $media->extension(null)->render(true));
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
