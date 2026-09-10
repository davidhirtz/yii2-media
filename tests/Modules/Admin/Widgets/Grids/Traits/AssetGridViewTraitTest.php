<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Grids\Traits;

use Hirtz\Media\Modules\Admin\Widgets\Grids\Traits\AssetGridViewTrait;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Skeleton\Widgets\Grids\GridView;

class AssetGridViewTraitTest extends TestCase
{
    use MediaFixtureTrait;

    public function testGetDimensionsColumn(): void
    {
        $grid = TestAssetGridView::make();

        $asset = TestAsset::create();
        $asset->populateFileRelation($this->getFileFromFixture('file-1'));

        self::assertEquals(
            '<td>1000 x 1000</td>',
            (string)$grid->getDimensionsColumn()->renderBody($asset, 0, 0),
        );
    }

    public function testGetDimensionsColumnWithoutDimensions(): void
    {
        $grid = TestAssetGridView::make();

        $file = $this->getFileFromFixture('file-1');
        $file->width = 0;
        $file->height = 0;

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);

        self::assertEquals(
            '<td>-</td>',
            (string)$grid->getDimensionsColumn()->renderBody($asset, 0, 0),
        );
    }
}

class TestAssetGridView extends GridView
{
    use AssetGridViewTrait {
        getDimensionsColumn as public;
    }
}
