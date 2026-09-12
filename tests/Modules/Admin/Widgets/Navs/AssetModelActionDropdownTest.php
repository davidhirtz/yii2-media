<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Modules\Admin\Data\AssetArrayDataProvider;
use Hirtz\Media\Modules\Admin\Widgets\Grids\AssetGridView;
use Hirtz\Media\Modules\Admin\Widgets\Navs\AssetModelActionDropdown;
use Hirtz\Media\Test\Models\TestAssetModel;
use Hirtz\Media\Test\TestCase;
use Yii;

class AssetModelActionDropdownTest extends TestCase
{
    /**
     * The upload swaps its target with the element of the same id in the response, so the button must name the asset
     * grid that is on the page, not the file grid of the create route.
     */
    public function testFileUploadTargetsRenderedAssetGrid(): void
    {
        $provider = $this->getProvider();

        $dropdown = (string)AssetModelActionDropdown::make()
            ->provider($provider);

        self::assertStringContainsString('data-target="#' . AssetGridView::ID . '"', $dropdown);

        $grid = (string)AssetGridView::make()
            ->provider($provider);

        self::assertStringContainsString('id="' . AssetGridView::ID . '"', $grid);
    }

    protected function getProvider(): AssetArrayDataProvider
    {
        $model = TestAssetModel::create();
        $model->id = 1;

        return Yii::$container->get(AssetArrayDataProvider::class, config: [
            'model' => $model,
        ]);
    }
}
