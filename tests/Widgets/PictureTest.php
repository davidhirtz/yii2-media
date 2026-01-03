<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Widgets;

use Hirtz\Media\Helpers\Html;
use Hirtz\Media\modules\ModuleTrait;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\FileFixtureTrait;
use Hirtz\Media\Widgets\Picture;

class PictureTest extends TestCase
{
    use FileFixtureTrait;
    use ModuleTrait;

    public function testTagOptions(): void
    {
        self::getModule()->transformations = [
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

        $file = $this->getFileFromFixture('file-2');

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $expected = Html::img($file->getUrl(), [
            'alt' => $file->alt_text,
            'loading' => 'lazy',
        ]);

        $picture = Picture::make();
        $picture->asset = $asset;
        $picture->transformations = ['md'];

        $this->assertEquals($expected, $picture->render());

        $picture->omitUnnecessaryPictureTag = false;

        $expected = Html::tag('picture', $expected);

        $this->assertEquals($expected, $picture->render(true));

        $file = $this->getFileFromFixture('file-1');
        $asset->populateFileRelation($file);

        $picture->transformations = ['xs'];

        $match = Html::tag('source', '', [
            'type' => 'image/webp',
            'srcset' => '/uploads/default/xs/test-1.webp',
        ]);

        $this->assertStringContainsString($match, $picture->render(true));

        $picture->sizes = '100vw';
        $picture->transformations = ['xs', 'md'];

        $match = Html::tag('source', '', [
            'type' => 'image/webp',
            'srcset' => '/uploads/default/xs/test-1.webp 400w,/uploads/default/md/test-1.webp 800w',
            'sizes' => '100vw',
        ]);

        $this->assertStringContainsString($match, $picture->render(true));
    }
}
