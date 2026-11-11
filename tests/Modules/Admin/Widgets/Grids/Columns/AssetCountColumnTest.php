<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Grids\Columns;

use Hirtz\Media\Modules\Admin\Widgets\Grids\Columns\AssetCountColumn;
use Hirtz\Media\Test\Models\TestAssetModel;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Widgets\Grids\GridView;
use yii\base\Model;
use yii\data\ArrayDataProvider;

class AssetCountColumnTest extends TestCase
{
    public function testTheColumnIsHiddenWhileNoRecordHasAnAsset(): void
    {
        self::assertFalse($this->createColumn(0, 0)->isVisible());
    }

    public function testTheColumnIsShownOnceARecordHasAnAsset(): void
    {
        self::assertTrue($this->createColumn(0, 2)->isVisible());
    }

    private function createColumn(int ...$assetCounts): AssetCountColumn
    {
        $models = [];

        foreach ($assetCounts as $assetCount) {
            $model = TestAssetModel::create();
            $model->id = count($models) + 1;
            $model->asset_count = $assetCount;
            $models[] = $model;
        }

        $grid = AssetCountColumnTestGridView::make()
            ->provider(new ArrayDataProvider(['allModels' => $models, 'key' => 'id']));

        return AssetCountColumn::make()->grid($grid);
    }
}

/**
 * @extends GridView<Model>
 */
class AssetCountColumnTestGridView extends GridView
{
}
