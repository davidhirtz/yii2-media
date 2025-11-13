<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\tests\unit\modules\admin\widgets\traits;

use Codeception\Test\Unit;
use davidhirtz\yii2\media\models\interfaces\AssetParentInterface;
use davidhirtz\yii2\media\modules\admin\widgets\grids\traits\AssetColumnsTrait;
use davidhirtz\yii2\skeleton\widgets\grids\GridView;

/**
 * @todo Implement tests.
 */

class AssetColumnsTraitTest extends Unit
{
}

class TestAssetColumnsGridView extends GridView
{
    use AssetColumnsTrait;

    public AssetParentInterface $parent;

    #[\Override]
    public function init(): void
    {
        $this->dataProvider = $this->getAssetActiveDataProvider();

        $this->columns = [
            $this->dimensionsColumn(),
        ];

        parent::init();
    }
}
