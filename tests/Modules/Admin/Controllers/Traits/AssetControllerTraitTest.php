<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Controllers\Traits;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Types\AssetModelType;
use Hirtz\Media\Modules\Admin\Controllers\Traits\AssetControllerTrait;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\Models\TestAssetModel;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Media\Modules\Admin\Module;
use Hirtz\Skeleton\Helpers\Html;
use Hirtz\Skeleton\Web\Controller;
use Override;
use PHPUnit\Framework\Attributes\TestWith;
use Yii;
use yii\helpers\Url;
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
        self::assertInstanceOf(Module::class, $module);

        $this->controller = new TestAssetController('test-asset', $module);
        Yii::$app->controller = $this->controller;
    }

    public function testTheWritingActionsArePostOnly(): void
    {
        self::assertSame([
            'delete' => ['post'],
            'delete-all' => ['post'],
            'order' => ['post'],
            'remove' => ['post'],
            'status' => ['post'],
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

    /**
     * The type is folded into the model's own answer, so an action cannot honour the installation's flag and miss
     * the type's — which is what a caller reading the module flag by hand used to do.
     */
    public function testFindAssetModelRefusesAModelWhoseTypeHasNoAssets(): void
    {
        $model = new TestAssetModelWithoutAssetTypes();
        $model->type = TestAssetModelWithoutAssetTypes::TYPE_DEFAULT;

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

        $route = $asset->getAdminRoute();
        self::assertNotFalse($route);

        // The picker leads back into itself, so the flash is the only way to the asset that was just created.
        self::assertSame(
            [Yii::t('media', 'ASSET_SUCCESS_CREATED', [
                'name' => '<a href="' . Url::to($route) . '">' . Html::encode($file->getAdminName()) . '</a>',
            ])],
            $this->getWebSession()->getFlash('success'),
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
        $this->getWebRequest()->setBodyParams(['Asset' => ['name' => 'Updated']]);

        $response = $this->controller->updateAsset($asset);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('Updated', TestAsset::findOne($asset->id)->name);
        self::assertSame(
            [Yii::t('media', 'ASSET_SUCCESS_UPDATED')],
            $this->getWebSession()->getFlash('success'),
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
            $this->getWebSession()->getFlash('success'),
        );
    }

    /**
     * The picker's remove button, which leads back into the picker rather than out of it.
     */
    public function testRemoveAssetDeletesTheModelsAssetForTheFile(): void
    {
        $model = $this->createModel();
        $asset = $this->insertAsset($model);
        $kept = $this->insertAsset($model, 'file-2');

        $response = $this->controller->removeAsset($model, $asset->file_id);

        self::assertInstanceOf(Response::class, $response);
        self::assertStringContainsString(
            '/admin/media/asset/create?testassetmodel=1',
            (string)$response->getHeaders()->get('Location'),
        );

        self::assertNull(TestAsset::findOne($asset->id));
        self::assertNotNull(TestAsset::findOne($kept->id));
        self::assertSame(
            [Yii::t('media', 'ASSET_SUCCESS_DELETED')],
            $this->getWebSession()->getFlash('success'),
        );
    }

    /**
     * The picker builds every link it renders — the folder dropdown, the search, the pager, the sort headers — off
     * the current request, so the toggle has to answer with a redirect to the picker's own route rather than render
     * it under the route the button posted to. The filter travels with it, or the first file a user adds throws
     * them back to the unfiltered library.
     */
    #[TestWith(['removeAsset'])]
    #[TestWith(['createAsset'])]
    public function testTheToggleRedirectsToTheFilteredPicker(string $method): void
    {
        $model = $this->createModel();
        $asset = $this->insertAsset($model);

        $_SERVER['REQUEST_METHOD'] = 'POST';

        $response = $this->controller->$method($model, $asset->file_id, 2, 'keyword');

        self::assertInstanceOf(Response::class, $response);
        self::assertStringEndsWith(
            '/admin/media/asset/create?testassetmodel=1&folder=2&q=keyword',
            (string)$response->getHeaders()->get('Location'),
        );
    }

    public function testRemoveAssetRefusesAFileTheModelDoesNotHave(): void
    {
        $model = $this->createModel();
        $this->insertAsset($model);

        $this->expectException(NotFoundHttpException::class);
        $this->controller->removeAsset($model, $this->getFileFromFixture('file-2')->id);
    }

    public function testRemoveAssetRefusesNoFile(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller->removeAsset($this->createModel());
    }

    /**
     * A model holds a file once, so the second insert is refused rather than adding another row.
     */
    public function testTheSameFileCannotBeInsertedTwice(): void
    {
        $model = $this->createModel();
        $file = $this->getFileFromFixture('file-1');

        $this->controller->insertAsset($model, $file);
        $this->controller->insertAsset($model, $file);

        self::assertSame(1, (int)TestAsset::find()->count());
    }

    public function testReorderAssets(): void
    {
        $model = $this->createModel();
        $first = $this->insertAsset($model, 'file-1');
        $second = $this->insertAsset($model, 'file-2');

        $this->getWebRequest()->setBodyParams(['asset' => [$second->id, $first->id]]);

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

/**
 * @extends Controller<Module>
 */
class TestAssetController extends Controller
{
    use AssetControllerTrait {
        createAsset as public;
        deleteAsset as public;
        findAsset as public;
        findAssetModel as public;
        getAssetVerbs as public;
        insertAsset as public;
        removeAsset as public;
        redirectToModel as public;
        reorderAssets as public;
        replaceAssetFile as public;
        updateAsset as public;
    }

    /**
     * The controller is a scratch one and has no views, so the name of the one a method reached for is what a test
     * asserts on.
     *
     * @param array<string, mixed> $params
     */
    #[Override]
    public function render($view, $params = []): string
    {
        return $view;
    }
}

class TestAssetModelWithoutAssets extends TestAssetModel
{
    #[Override]
    public function allowsAssets(): bool
    {
        return false;
    }
}

class TestAssetModelWithoutAssetTypes extends TestAssetModel
{
    /**
     * @return list<AssetModelType>
     */
    #[Override]
    public function getTypes(): array
    {
        return [
            AssetModelType::make(self::TYPE_DEFAULT)
                ->name('Default')
                ->allowAssets(false),
        ];
    }
}
