<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Grids;

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Data\FileActiveDataProvider;
use Hirtz\Media\Modules\Admin\Widgets\Grids\FileGridView;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\Models\TestAssetModel;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Override;
use Yii;
use yii\web\Controller;

/**
 * The picker is the grid a file is added to a record from. A record holds a file once, so its button is a toggle
 * rather than an add that keeps adding.
 */
class FileGridViewTest extends TestCase
{
    use MediaFixtureTrait;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Yii::$app->controller = new Controller('test', Yii::$app);
    }

    public function testAFileTheModelDoesNotHaveIsOffered(): void
    {
        $html = $this->renderPicker();

        self::assertStringContainsString('/admin/media/asset/create', $html);
        self::assertStringNotContainsString('/admin/media/asset/remove', $html);
        self::assertStringNotContainsString('is-selected', $html);
    }

    public function testAFileTheModelAlreadyHasIsRemoved(): void
    {
        $file = $this->getFileFromFixture('file-1');
        $html = $this->renderPicker($file);

        self::assertStringContainsString("asset/remove?testassetmodel=1&amp;file=$file->id", $html);
        self::assertStringNotContainsString("asset/create?testassetmodel=1&amp;file=$file->id", $html);
        self::assertStringContainsString('is-selected', $html);
    }

    /**
     * Replacing an asset's file offers no button for a file the model already holds — its own, which would be a
     * no-op, and another asset's, which the uniqueness rule refuses.
     */
    public function testReplacingAFileOffersNoButtonForAFileTheModelHas(): void
    {
        $file = $this->getFileFromFixture('file-1');
        $other = $this->getFileFromFixture('file-2');

        $model = $this->createModel($file, $other);
        $asset = $model->assets[0];

        $html = (string)FileGridView::make()
            ->model($model)
            ->asset($asset)
            ->provider(Yii::createObject(FileActiveDataProvider::class));

        self::assertStringNotContainsString("asset/create?testassetmodel=1&amp;file=$file->id", $html);
        self::assertStringNotContainsString("asset/create?testassetmodel=1&amp;file=$other->id", $html);
        self::assertStringNotContainsString('/admin/media/asset/remove', $html);

        // a third file the model does not have is still offered as the replacement
        self::assertStringContainsString('/admin/media/asset/create', $html);
        self::assertStringContainsString("asset=$asset->id", $html);
    }

    private function renderPicker(?File ...$files): string
    {
        return (string)FileGridView::make()
            ->model($this->createModel(...$files))
            ->provider(Yii::createObject(FileActiveDataProvider::class));
    }

    private function createModel(?File ...$files): TestAssetModel
    {
        $model = TestAssetModel::create();
        $model->setAttributes(['id' => 1, 'asset_count' => count($files)], false);

        $assets = [];
        $id = 0;

        foreach (array_filter($files) as $file) {
            $asset = TestAsset::create();
            $asset->setAttributes(['id' => ++$id, 'file_id' => $file->id], false);
            $assets[] = $asset;
        }

        $model->populateRelation('assets', $assets);

        return $model;
    }
}
