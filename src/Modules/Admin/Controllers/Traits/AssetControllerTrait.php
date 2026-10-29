<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Controllers\Traits;

use Hirtz\Media\Models\Actions\DeleteAssets;
use Hirtz\Media\Models\Actions\DuplicateAsset;
use Hirtz\Media\Models\Actions\InsertAsset;
use Hirtz\Media\Models\Actions\ReorderAssets;
use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\Admin\Data\AssetArrayDataProvider;
use Hirtz\Media\Modules\Admin\Data\FileActiveDataProvider;
use Hirtz\Media\Modules\Admin\Widgets\Grids\FileGridView;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Skeleton\Helpers\Html;
use Hirtz\Skeleton\Html\A;
use Hirtz\Skeleton\I18n\Message;
use Hirtz\Skeleton\Web\Application;
use Hirtz\Skeleton\Web\Controller;
use Hirtz\Skeleton\Web\Traits\StatusControllerTrait;
use Hirtz\Skeleton\Widgets\Flashes;
use yii\base\Module;
use Yii;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;
use Stringable;
use yii\web\Response;

/**
 * Holds no access rules: a controller serves one model, authorises the record and calls the matching method.
 *
 * @mixin Controller<Module>
 */
trait AssetControllerTrait
{
    use FileControllerTrait;
    use StatusControllerTrait;

    /**
     * @return array{class: class-string, actions: array<string, list<string>>}
     */
    protected function getAssetVerbs(): array
    {
        return [
            'class' => VerbFilter::class,
            'actions' => [
                'delete' => ['post'],
                'delete-all' => ['post'],
                'duplicate' => ['post'],
                'remove' => ['post'],
                'reorder' => ['post'],
                'status' => ['post'],
            ],
        ];
    }

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

    /**
     * The picked file replaces the file of `$asset` instead of adding another asset.
     */
    protected function createAsset(
        AssetModelInterface $model,
        ?int $file = null,
        ?int $folder = null,
        ?string $q = null,
        ?Asset $asset = null,
    ): Response|string {
        if ($this->request->getIsPost()) {
            $isUploaded = !$file;

            if ($file) {
                $file = $this->findFile($file);
            }

            $file ??= $this->insertFileFromRequest($folder);

            if (!$this->response->getIsOk()) {
                return $this->response;
            }

            // A refused import answered nothing, which the form swapped over the whole page.
            if (!$file || $file->hasErrors()) {
                return $this->redirect($model->getAssetClass()::getAdminIndexRoute($model));
            }

            if ($asset) {
                return $this->replaceAssetFile($asset, $file);
            }

            $this->insertAsset($model, $file);

            // Every file of a multiple upload but the last asks for no content, and still needs its asset.
            if ($this->request->preferNoContent()) {
                $this->response->setStatusCode(204);
                return $this->response;
            }

            // An upload or import is triggered from the asset grid, which is only rendered by the index.
            return $isUploaded
                ? $this->renderIndex($model)
                : $this->redirectToAssetPicker($model, $folder, $q);
        }

        return $this->renderAssetPicker($model, $folder, $q, $asset);
    }

    /**
     * A model holds a file once, so the button of a file it already has removes that asset.
     */
    protected function removeAsset(
        AssetModelInterface $model,
        ?int $file = null,
        ?int $folder = null,
        ?string $q = null,
    ): Response|string {
        $asset = $file
            ? $model->getAssets()->andWhere(['file_id' => $file])->one()
            : null;

        if (!$asset) {
            throw new NotFoundHttpException();
        }

        // the model is already loaded, so the recount in `afterDelete()` needs no second query for it
        $asset->populateModelRelation($model);
        $asset->delete();

        $this->errorOrSuccess($asset, Yii::t('media', 'ASSET_REMOVE_SUCCESS'));

        return $this->redirectToAssetPicker($model, $folder, $q);
    }

    /**
     * It redirects rather than renders, since the picker builds its links off the current request, which for the
     * POST-only `remove` is a 405.
     */
    protected function redirectToAssetPicker(
        AssetModelInterface $model,
        ?int $folder = null,
        ?string $q = null,
    ): Response {
        // The toggle swaps the grid alone, so the redirect is an ordinary one it follows itself.
        Application::current()->getResponse()->setHtmxRedirectTarget(null);

        return $this->redirect([
            ...$model->getAssetClass()::getAdminCreateRoute($model),
            'folder' => $folder,
            'q' => $q,
            ...array_intersect_key($this->request->getQueryParams(), array_flip(FileGridView::PICKER_LIST_PARAMS)),
        ]);
    }

    protected function renderAssetPicker(
        AssetModelInterface $model,
        ?int $folder = null,
        ?string $q = null,
        ?Asset $asset = null,
    ): string {
        $provider = Yii::$container->get(FileActiveDataProvider::class, config: [
            'folder' => Folder::findOne($folder),
            'search' => $q,
        ]);

        return $this->render('create', [
            'asset' => $asset,
            'model' => $model,
            'provider' => $provider,
        ]);
    }

    protected function insertAsset(AssetModelInterface $model, File $file): void
    {
        $action = InsertAsset::create($model, $file);

        if (!$action->isInserted()) {
            $this->error($action->getAsset());
            return;
        }

        $this->success($this->getAssetCreatedMessage($action->getAsset()));
    }

    /**
     * A `Message`, since a flash encodes what it is handed and trusts only a `Stringable` (#160).
     */
    protected function getAssetCreatedMessage(Asset $asset): Stringable
    {
        $route = $asset->getAdminRoute();
        $name = $asset->file->getAdminName();

        $link = $route
            ? (string)A::make()->href(Url::to($route))->text($name)
            : Html::encode($name);

        return Message::make('media', 'ASSET_CREATE_SUCCESS', ['name' => $link]);
    }

    protected function replaceAssetFile(Asset $asset, File $file): Response
    {
        $asset->populateFileRelation($file);
        $asset->update();

        $this->errorOrSuccess($asset, Yii::t('media', 'ASSET_REPLACE_SUCCESS'));

        return $this->redirect(['update', 'id' => $asset->id]);
    }

    protected function updateAsset(Asset $asset): Response|string
    {
        if ($asset->load($this->request->post()) && !$this->request->isFormReload()) {
            if ($asset->update()) {
                $this->success(Yii::t('media', 'ASSET_UPDATE_SUCCESS'));
            }

            if (!$asset->hasErrors()) {
                return $this->refresh();
            }
        }

        return $this->render('update', [
            'asset' => $asset,
        ]);
    }

    /**
     * A copy goes to another record, never the same one: a model holds a file once, so the duplicate fails there.
     */
    protected function duplicateAsset(Asset $asset, AssetModelInterface $model): Response
    {
        $duplicate = DuplicateAsset::create([
            'asset' => $asset,
            'model' => $model,
        ]);

        if ($errors = $duplicate->getFirstErrors()) {
            $this->error($errors);
            return $this->redirect(['update', 'id' => $asset->id]);
        }

        $this->success(Yii::t('media', 'ASSET_DUPLICATE_SUCCESS'));
        return $this->redirect(['update', 'id' => $duplicate->id]);
    }

    protected function deleteAsset(Asset $asset): Response|string
    {
        $asset->delete();
        $this->errorOrSuccess($asset, Yii::t('media', 'ASSET_REMOVE_SUCCESS'));

        return $this->redirectToModel($asset);
    }

    /**
     * The selection is filtered through the model's own relation, so a posted id of another record is dropped.
     */
    protected function deleteAssets(AssetModelInterface $model): Response
    {
        $ids = array_map(intval(...), (array)$this->request->post('selection', []));
        $assets = $ids ? $model->getAssets()->andWhere(['id' => $ids])->all() : [];

        if ($assets) {
            $action = DeleteAssets::create($model, $assets);

            if ($count = count($action->getDeleted())) {
                $this->success(Yii::t('media', 'ASSET_REMOVE_SELECTED_SUCCESS', ['count' => $count]));
            }

            foreach ($action->getFailed() as $asset) {
                $this->error($asset);
            }
        }

        return $this->redirect($model->getAssetClass()::getAdminIndexRoute($model));
    }

    protected function reorderAssets(AssetModelInterface $model): string
    {
        $success = ReorderAssets::runWithBodyParam('asset', [
            'model' => $model,
        ]);

        if ($success) {
            $this->success(Yii::t('media', 'ASSET_ORDER_SUCCESS'));
        }

        return (string)Flashes::make();
    }

    /**
     * @param class-string<Asset> ...$assetClasses
     */
    protected function findAsset(int $id, string ...$assetClasses): Asset
    {
        $asset = Asset::findOne($id);

        if (!$asset || ($assetClasses && !array_filter($assetClasses, fn (string $class): bool => $asset instanceof $class))) {
            throw new NotFoundHttpException();
        }

        return $asset;
    }

    protected function findAssetModel(?AssetModelInterface $model): AssetModelInterface
    {
        if (!$model || !$model->allowsAssets()) {
            throw new NotFoundHttpException();
        }

        return $model;
    }

    protected function redirectToModel(Asset $asset): Response
    {
        return $this->redirect([
            ...$asset->getAdminModelRoute(),
            '#' => "asset-$asset->id",
        ]);
    }
}
