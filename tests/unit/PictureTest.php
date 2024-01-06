<?php

namespace davidhirtz\yii2\media\tests\unit;

use Codeception\Test\Unit;
use davidhirtz\yii2\media\helpers\Html;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\tests\unit\models\TestAsset;
use davidhirtz\yii2\media\widgets\Picture;

class PictureTest extends Unit
{
    public function testTagOptions()
    {
        $file = File::create();
        $file->alt_text = 'Image Alt Text';
        $file->basename = 'image';
        $file->extension = 'jpg';
        $file->width = 200;
        $file->height = 100;
        $file->populateFolderRelation(Folder::getDefault());

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        $expected = Html::img($file->getUrl(), [
            'alt' => $file->alt_text,
            'loading' => 'lazy',
        ]);

        $this->assertEquals($expected, Picture::tag($asset, [
            'transformations' => ['md'],
        ]));

        $expected = Html::tag('picture', $expected);

        $this->assertEquals($expected, Picture::tag($asset, [
            'transformations' => ['md'],
            'omitUnnecessaryPictureTag' => false,
        ]));

        $match = Html::tag('source', '', [
            'type' => 'image/webp',
            'srcset' => '/uploads/default/xs/image.webp 100w,/uploads/default/sm/image.webp 200w',
            'sizes' => '100vw',
        ]);

        $this->assertStringContainsString($match, Picture::tag($asset, [
            'transformations' => ['xs', 'sm'],
        ]));
    }
}
