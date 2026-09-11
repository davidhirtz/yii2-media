<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Controllers;

use Hirtz\Media\Models\Actions\DuplicateAsset;
use Hirtz\Media\Models\Actions\ReorderAssets;
use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Media\Modules\Admin\Controllers\Traits\FileControllerTrait;
use Hirtz\Media\Modules\Admin\Data\AssetArrayDataProvider;
use Hirtz\Media\Modules\Admin\Data\FileActiveDataProvider;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\I18n\Lang;
use Hirtz\Skeleton\Web\Controller;
use Hirtz\Skeleton\Widgets\Flashes;
use Override;
use Yii;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * The bodies of the asset actions, without any opinion on who may run them. A controller declares its own access
 * rules, resolves and authorises the model or the asset the way its bundle does, and calls the matching method.
 */
abstract class AbstractAssetController extends Controller
{
    use FileControllerTrait;
    use ModuleTrait;

    #[Override]
    public function behaviors(): array
    {
        return [
            ...parent::behaviors(),
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                    'duplicate' => ['post'],
                    'order' => ['post'],
                ],
            ],
        ];
    }

    abstract public function actionIndex(): Response|string;

    abstract public function actionCreate(): Response|string;

    abstract public function actionUpdate(int $id): Response|string;

    abstract public function actionDelete(int $id): Response|string;

    abstract public function actionDuplicate(int $id): Response|string;

    abstract public function actionOrder(): string;

    protected function renderIndex(AssetModelInterface $model): Response|string
    {
        $provider = Yii::$container->get(AssetArrayDataProvider::class, config: [
            'model' => $model,
        ]);

        return $this->render('index', [
            'model' => $model,
            'provider' => $provider,
        ]);
    }

    protected function createAsset(
        AssetModelInterface $model,
        ?int $file = null,
        ?int $folder = null,
        ?string $q = null
    ): Response|string {
        if ($this->request->getIsPost()) {
            if ($file) {
                $file = $this->findFile($file);
            }

            $file ??= $this->insertFileFromRequest($folder);

            if ($this->request->preferNoContent()) {
                $this->response->setStatusCode(204);
            }

            if (!$this->response->getIsOk() || $file->hasErrors()) {
                return $this->response;
            }

            $asset = Yii::createObject($model->getAssetClass());
            $asset->loadDefaultValues();
            $asset->populateModelRelation($model);
            $asset->populateFileRelation($file);
            $asset->insert();

            $this->errorOrSuccess($asset, Lang::t('media', 'ASSET_SUCCESS_CREATED'));
        }

        $provider = Yii::$container->get(FileActiveDataProvider::class, config: [
            'folder' => Folder::findOne($folder),
            'search' => $q,
        ]);

        return $this->render('create', [
            'model' => $model,
            'provider' => $provider,
        ]);
    }

    protected function updateAsset(Asset $asset): Response|string
    {
        if ($asset->load($this->request->post()) && !$this->request->isFormReload()) {
            if ($asset->update()) {
                $this->success(Lang::t('media', 'ASSET_SUCCESS_UPDATED'));
            }

            return $this->redirectToModel($asset);
        }

        return $this->render('update', [
            'asset' => $asset,
        ]);
    }

    protected function deleteAsset(Asset $asset): Response|string
    {
        $asset->delete();
        $this->errorOrSuccess($asset, Lang::t('media', 'ASSET_SUCCESS_DELETED'));

        return $this->redirectToModel($asset);
    }

    protected function duplicateAsset(Asset $asset): Response|string
    {
        $duplicate = DuplicateAsset::create([
            'asset' => $asset,
        ]);

        if ($errors = $duplicate->getFirstErrors()) {
            $this->error($errors);
            return $this->redirect(['update', 'id' => $asset->id]);
        }

        $this->success(Lang::t('media', 'ASSET_SUCCESS_DUPLICATED'));
        return $this->redirect(['update', 'id' => $duplicate->id]);
    }

    protected function reorderAssets(AssetModelInterface $model): string
    {
        $success = ReorderAssets::runWithBodyParam('asset', [
            'model' => $model,
        ]);

        if ($success) {
            $this->success(Lang::t('media', 'ASSET_SUCCESS_ORDERED'));
        }

        return (string)Flashes::make();
    }

    /**
     * @param class-string<Asset> ...$assetClasses the classes this controller serves
     */
    protected function findAsset(int $id, string ...$assetClasses): Asset
    {
        $asset = Asset::findOne($id);

        if (!$asset || !in_array($asset::class, $assetClasses, true)) {
            throw new NotFoundHttpException();
        }

        return $asset;
    }

    protected function findAssetModel(AssetModelInterface|null $model): AssetModelInterface
    {
        if (!$model || !$model->hasAssetsEnabled()) {
            throw new NotFoundHttpException();
        }

        return $model;
    }

    protected function redirectToModel(Asset $asset): Response
    {
        $model = $asset->model;

        return $this->redirect([
            'index',
            $model->getParamName() => $model->id,
            '#' => "asset-$asset->id",
        ]);
    }
}
