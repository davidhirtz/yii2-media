<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Controllers\Traits;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Controllers\Traits\AssetControllerTrait;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\Models\TestAssetModel;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Skeleton\Web\Controller;
use Override;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * The trait carries the bodies of the asset actions; the controllers that use it live in the bundles that own an
 * asset model, so a scratch one stands in for them here.
 */
class AssetControllerTraitTest extends TestCase
{
    use MediaFixtureTrait;

    private TestAssetController $controller;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $module = Yii::$app->getModule('admin')->getModule('media');

        $this->controller = new TestAssetController('test-asset', $module);
        Yii::$app->controller = $this->controller;
    }

    public function testTheWritingActionsArePostOnly(): void
    {
        self::assertSame([
            'delete' => ['post'],
            'duplicate' => ['post'],
            'order' => ['post'],
        ], $this->controller->getAssetVerbs()['actions']);
    }

    public function testFindAssetModelRefusesNothing(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller->findAssetModel(null);
    }

    public function testFindAssetModelRefusesAModelWithoutAssets(): void
    {
        $model = new TestAssetModelWithoutAssets();

        $this->expectException(NotFoundHttpException::class);
        $this->controller->findAssetModel($model);
    }

    public function testFindAssetRefusesAnotherClass(): void
    {
        $asset = $this->insertAsset();

        self::assertSame($asset->id, $this->controller->findAsset($asset->id, TestAsset::class)->id);

        $this->expectException(NotFoundHttpException::class);
        $this->controller->findAsset($asset->id, Asset::class);
    }

    public function testFindAssetRefusesAnUnknownId(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller->findAsset(99999, TestAsset::class);
    }

    public function testInsertAsset(): void
    {
        $model = $this->createModel();
        $file = $this->getFileFromFixture('file-1');

        $this->controller->insertAsset($model, $file);

        $asset = TestAsset::findOne(['model_id' => $model->id]);

        self::assertNotNull($asset);
        self::assertSame($file->id, $asset->file_id);
        self::assertSame(
            [Yii::t('media', 'ASSET_SUCCESS_CREATED')],
            Yii::$app->getSession()->getFlash('success'),
        );
    }

    /**
     * Replacing the file is what separates the create action's `asset` parameter from an ordinary insert: the record
     * keeps its id and everything it carries.
     */
    public function testReplaceAssetFileKeepsTheRecord(): void
    {
        $asset = $this->insertAsset();
        $asset->name = 'Name';
        self::assertTrue($asset->update() !== false);

        $file = $this->getFileFromFixture('file-2');
        $response = $this->controller->replaceAssetFile($asset, $file);

        self::assertStringContainsString("test-asset/update", (string)$response->getHeaders()->get('Location'));

        $updated = TestAsset::findOne($asset->id);

        self::assertNotNull($updated);
        self::assertSame($file->id, $updated->file_id);
        self::assertSame('Name', $updated->name);
        self::assertSame(1, (int)TestAsset::find()->count());
    }

    public function testCreateAssetWithAnAssetReplacesItsFile(): void
    {
        $asset = $this->insertAsset();
        $file = $this->getFileFromFixture('file-2');

        $_SERVER['REQUEST_METHOD'] = 'POST';

        $response = $this->controller->createAsset($asset->model, $file->id, asset: $asset);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame($file->id, TestAsset::findOne($asset->id)->file_id);
    }

    public function testUpdateAsset(): void
    {
        $asset = $this->insertAsset();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        Yii::$app->getRequest()->setBodyParams(['Asset' => ['name' => 'Updated']]);

        $response = $this->controller->updateAsset($asset);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('Updated', TestAsset::findOne($asset->id)->name);
        self::assertSame(
            [Yii::t('media', 'ASSET_SUCCESS_UPDATED')],
            Yii::$app->getSession()->getFlash('success'),
        );
    }

    public function testDeleteAsset(): void
    {
        $asset = $this->insertAsset();

        $response = $this->controller->deleteAsset($asset);

        self::assertInstanceOf(Response::class, $response);
        self::assertNull(TestAsset::findOne($asset->id));
        self::assertSame(
            [Yii::t('media', 'ASSET_SUCCESS_DELETED')],
            Yii::$app->getSession()->getFlash('success'),
        );
    }

    public function testDuplicateAsset(): void
    {
        $asset = $this->insertAsset();

        $response = $this->controller->duplicateAsset($asset);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(2, (int)TestAsset::find()->count());

        $duplicate = TestAsset::find()
            ->andWhere(['not', ['id' => $asset->id]])
            ->one();

        self::assertNotNull($duplicate);
        self::assertSame($asset->file_id, $duplicate->file_id);
        self::assertSame(Asset::STATUS_DRAFT, $duplicate->status);
    }

    public function testReorderAssets(): void
    {
        $model = $this->createModel();
        $first = $this->insertAsset($model, 'file-1');
        $second = $this->insertAsset($model, 'file-2');

        Yii::$app->getRequest()->setBodyParams(['asset' => [$second->id, $first->id]]);

        $this->controller->reorderAssets($model);

        self::assertSame(2, TestAsset::findOne($first->id)->position);
        self::assertSame(1, TestAsset::findOne($second->id)->position);
    }

    public function testRedirectToModelAnchorsTheAsset(): void
    {
        $asset = $this->insertAsset();

        $location = (string)$this->controller->redirectToModel($asset)
            ->getHeaders()
            ->get('Location');

        self::assertStringContainsString('/admin/media/asset/index', $location);
        self::assertStringEndsWith("#asset-$asset->id", $location);
    }

    private function createModel(int $id = 1): TestAssetModel
    {
        $model = TestAssetModel::create();
        $model->id = $id;

        return $model;
    }

    private function insertAsset(?TestAssetModel $model = null, string $key = 'file-1'): TestAsset
    {
        $asset = TestAsset::create();
        $asset->loadDefaultValues();
        $asset->populateModelRelation($model ?? $this->createModel());
        $asset->populateFileRelation($this->getFileFromFixture($key));

        self::assertTrue($asset->insert(), implode(' ', $asset->getErrorSummary(true)));

        return $asset;
    }
}

class TestAssetController extends Controller
{
    use AssetControllerTrait {
        createAsset as public;
        deleteAsset as public;
        duplicateAsset as public;
        findAsset as public;
        findAssetModel as public;
        getAssetVerbs as public;
        insertAsset as public;
        redirectToModel as public;
        reorderAssets as public;
        replaceAssetFile as public;
        updateAsset as public;
    }
}

class TestAssetModelWithoutAssets extends TestAssetModel
{
    #[Override]
    public function hasAssetsEnabled(): bool
    {
        return false;
    }
}
