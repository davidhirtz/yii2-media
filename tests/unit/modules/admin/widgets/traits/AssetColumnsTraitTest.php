<?php

declare(strict_types=1);

namespace Hirtz\Media\tests\unit\Modules\Admin\Widgets\Traits;

use Codeception\Test\Unit;
use Hirtz\Media\Models\interfaces\AssetParentInterface;
use Hirtz\Media\Modules\Admin\Widgets\Grids\Traits\AssetGridViewTrait;
use Hirtz\Skeleton\Widgets\Grids\GridView;

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
