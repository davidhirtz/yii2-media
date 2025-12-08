<?php

declare(strict_types=1);

namespace Hirtz\Media\tests\unit\modules\admin\widgets\traits;

use Codeception\Test\Unit;
use Hirtz\Media\models\interfaces\AssetParentInterface;
use Hirtz\Media\modules\admin\widgets\grids\traits\AssetGridViewTrait;
use Hirtz\Skeleton\widgets\grids\GridView;

/**
 * @todo Implement tests.
 */

class AssetColumnsTraitTest extends Unit
{
}

class TestAssetColumnsGridView extends GridView
{
    use AssetGridViewTrait;

    public AssetParentInterface $parent;

    #[\Override]
    public function configure(): void
    {
        $this->provider = $this->getAssetActiveDataProvider();

        $this->columns = [
            $this->getDimensionsColumn(),
        ];

        parent::configure();
    }
}
