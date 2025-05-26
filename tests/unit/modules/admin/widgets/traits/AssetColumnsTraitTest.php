<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\tests\unit\modules\admin\widgets\traits;

use Codeception\Test\Unit;
use davidhirtz\yii2\media\modules\admin\widgets\grids\traits\AssetColumnsTrait;
use davidhirtz\yii2\media\tests\data\models\TestAssetParent;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grids\GridView;

class AssetColumnsTraitTest extends Unit
{
    public function testDimensionsColumn()
    {
        $grid = new TestAssetColumnsGridView([
            'parent' => TestAssetParent::create()
        ]);
    }
}

class TestAssetColumnsGridView extends GridView
{
    use AssetColumnsTrait;

    public function init(): void
    {
        $this->dataProvider = $this->getAssetActiveDataProvider();

        $this->columns = [
            $this->dimensionsColumn(),
        ];

        $this->searchUrl = '/';

        parent::init();
    }
}
