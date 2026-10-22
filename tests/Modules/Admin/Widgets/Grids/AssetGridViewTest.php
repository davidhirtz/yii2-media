<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Grids;

use Hirtz\Media\Modules\Admin\Data\AssetArrayDataProvider;
use Hirtz\Media\Modules\Admin\Widgets\Grids\AssetGridView;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\Models\TestAssetModel;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Skeleton\Widgets\Grids\Columns\Column;
use Override;
use Yii;

class AssetGridViewTest extends TestCase
{
    use MediaFixtureTrait;

    public function testTheAssetsOfAModelAreReorderable(): void
    {
        $model = TestAssetModel::create();
        $model->id = 1;

        foreach (['file-1', 'file-2'] as $key) {
            $asset = TestAsset::create();
            $asset->loadDefaultValues();
            $asset->populateModelRelation($model);
            $asset->populateFileRelation($this->getFileFromFixture($key));

            self::assertTrue($asset->insert(), implode(' ', $asset->getErrorSummary(true)));
        }

        $provider = Yii::$container->get(AssetArrayDataProvider::class, config: [
            'model' => $model,
        ]);

        $grid = (string)AssetGridView::make()
            ->provider($provider);

        self::assertStringContainsString('data-reorder-url="', $grid);
    }

    public function testTheAltTextLinkAndEmbedUrlAreShownAsIcons(): void
    {
        $asset = $this->createAsset();
        $asset->link = 'https://example.com/Å?q=ą';
        $asset->embed_url = 'https://www.youtube.com/embed/abc';

        $html = $this->renderGrid($asset);

        self::assertStringContainsString('fa-check', $html);
        self::assertStringContainsString('title="https://example.com/Å?q=ą"', $html);
        self::assertStringContainsString('fa-link', $html);
        self::assertStringContainsString('title="https://www.youtube.com/embed/abc"', $html);
        self::assertStringContainsString('fa-code', $html);
    }

    public function testTheLinkAndEmbedUrlColumnsNeedAnAssetShowingThem(): void
    {
        $asset = new class () extends TestAsset {
            #[Override]
            protected function getDefaultCustomAttributes(): array
            {
                return [];
            }
        };

        $asset->setAttributes(['id' => 1, 'status' => TestAsset::STATUS_ENABLED], false);
        $asset->populateFileRelation($this->getFileFromFixture('file-1'));

        $html = $this->renderGrid($asset);

        self::assertStringNotContainsString('>Link<', $html);
        self::assertStringNotContainsString('>Embed URL<', $html);

        // The file's alt text is what the asset falls back to.
        self::assertStringContainsString('fa-check', $html);
    }

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

    private function createAsset(): TestAsset
    {
        $asset = TestAsset::create();
        $asset->setAttributes(['id' => 1, 'status' => TestAsset::STATUS_ENABLED], false);
        $asset->populateFileRelation($this->getFileFromFixture('file-1'));

        return $asset;
    }

    private function renderGrid(TestAsset $asset): string
    {
        $model = TestAssetModel::create();
        $model->id = 999;

        $provider = Yii::$container->get(AssetArrayDataProvider::class, config: ['model' => $model]);
        $provider->allModels = [$asset];

        return (string)AssetGridView::make()->provider($provider);
    }
}

/**
 * @extends AssetGridView<TestAsset>
 */
class TestAssetGridView extends AssetGridView
{
    #[Override]
    public function getDimensionsColumn(): ?Column
    {
        return parent::getDimensionsColumn();
    }
}
