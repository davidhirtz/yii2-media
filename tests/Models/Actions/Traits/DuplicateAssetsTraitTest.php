<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models\Actions\Traits;

use Hirtz\Media\Models\Actions\Traits\DuplicateAssetsTrait;
use Hirtz\Media\Models\Asset;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\Models\TestAssetModel;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Skeleton\Models\Actions\DuplicateActiveRecord;

class DuplicateAssetsTraitTest extends TestCase
{
    use MediaFixtureTrait;

    public function testTheAssetsAreCopiedOntoTheDuplicate(): void
    {
        $model = $this->createModel(1);

        $first = $this->createAsset($model, 'file-1');
        $first->status = Asset::STATUS_DISABLED;
        self::assertTrue($first->insert(), implode(' ', $first->getErrorSummary(true)));

        $second = $this->createAsset($model, 'file-2');
        self::assertTrue($second->insert(), implode(' ', $second->getErrorSummary(true)));

        $this->duplicateAssets($model, $this->createModel(2));

        $assets = TestAsset::find()
            ->where(['model_id' => 2])
            ->orderBy(['position' => SORT_ASC])
            ->all();

        self::assertCount(2, $assets);

        self::assertSame($first->file_id, $assets[0]->file_id);
        self::assertSame(Asset::STATUS_DISABLED, $assets[0]->status);
        self::assertSame(1, $assets[0]->position);

        self::assertSame($second->file_id, $assets[1]->file_id);
        self::assertSame($second->status, $assets[1]->status);
        self::assertSame(2, $assets[1]->position);
    }

    /**
     * The positions are renumbered from 1, so a gap in the source does not survive the copy.
     */
    public function testThePositionsAreRenumbered(): void
    {
        $model = $this->createModel(1);

        $asset = $this->createAsset($model, 'file-1');
        $asset->position = 7;
        self::assertTrue($asset->insert(), implode(' ', $asset->getErrorSummary(true)));

        $this->duplicateAssets($model, $this->createModel(2));

        $duplicate = TestAsset::findOne(['model_id' => 2]);

        self::assertNotNull($duplicate);
        self::assertSame(1, $duplicate->position);
    }

    public function testAModelWithoutAssetsCopiesNothing(): void
    {
        $this->duplicateAssets($this->createModel(1), $this->createModel(2));

        self::assertSame(0, (int)TestAsset::find()->where(['model_id' => 2])->count());
    }

    private function duplicateAssets(TestAssetModel $model, TestAssetModel $duplicate): void
    {
        $action = new DuplicateTestAssetModel($model);
        $action->duplicate = $duplicate;
        $action->duplicateAssets();
    }

    private function createModel(int $id): TestAssetModel
    {
        $model = TestAssetModel::create();
        $model->id = $id;

        // `DuplicateAsset` only adopts a model that is not a new record, and the trait runs after the insert.
        $model->setOldAttributes(['id' => $id]);

        return $model;
    }

    private function createAsset(TestAssetModel $model, string $key): TestAsset
    {
        $asset = TestAsset::create();
        $asset->loadDefaultValues();
        $asset->populateModelRelation($model);
        $asset->populateFileRelation($this->getFileFromFixture($key));

        return $asset;
    }
}

/**
 * @extends DuplicateActiveRecord<TestAssetModel>
 */
class DuplicateTestAssetModel extends DuplicateActiveRecord
{
    use DuplicateAssetsTrait {
        duplicateAssets as public;
    }
}
