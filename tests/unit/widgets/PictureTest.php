<?php

declare(strict_types=1);

namespace Hirtz\Media\tests\unit\widgets;

use Codeception\Test\Unit;
use Hirtz\Media\helpers\Html;
use Hirtz\Media\models\collections\FolderCollection;
use Hirtz\Media\models\File;
use Hirtz\Media\tests\data\models\TestAsset;
use Hirtz\Media\widgets\Picture;

class PictureTest extends Unit
{
    public function testTagOptions(): void
    {
        $file = File::create();
        $file->alt_text = 'Image Alt Text';
        $file->basename = 'image';
        $file->extension = 'jpg';
        $file->width = 200;
        $file->height = 100;
        $file->populateFolderRelation(FolderCollection::getDefault());

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $expected = Html::img($file->getUrl(), [
            'alt' => $file->alt_text,
            'loading' => 'lazy',
        ]);

        $this->assertEquals($expected, Picture::widget([
            'asset' => $asset,
            'transformations' => ['md'],
        ]));

        $expected = Html::tag('picture', $expected);

        $this->assertEquals($expected, Picture::widget([
            'asset' => $asset,
            'transformations' => ['md'],
            'omitUnnecessaryPictureTag' => false,
        ]));

        $match = Html::tag('source', '', [
            'type' => 'image/webp',
            'srcset' => '/uploads/default/xs/image.webp',
        ]);

        $this->assertStringContainsString($match, Picture::widget([
            'asset' => $asset,
            'transformations' => ['xs'],
        ]));

        $match = Html::tag('source', '', [
            'type' => 'image/webp',
            'srcset' => '/uploads/default/xs/image.webp 100w,/uploads/default/sm/image.webp 200w',
            'sizes' => '100vw',
        ]);

        $this->assertStringContainsString($match, Picture::widget([
            'asset' => $asset,
            'transformations' => ['xs', 'sm'],
        ]));
    }
}
