<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Grids\Traits;

use Hirtz\Media\Models\Interfaces\AssetParentInterface;
use Hirtz\Media\Modules\Admin\Widgets\Grids\Traits\AssetGridViewTrait;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Widgets\Grids\GridView;
use Override;

class AssetGridViewTraitTest extends TestCase
{
    public function testGetDimensionsColumn(): void
    {
        // Todo
    }
}

class TestAssetGridView extends GridView
{
    use AssetGridViewTrait;

    public AssetParentInterface $parent;

    #[Override]
    public function configure(): void
    {
        $this->provider = $this->getAssetActiveDataProvider();

        $this->columns = [
            $this->getDimensionsColumn(),
        ];

        parent::configure();
    }
}
