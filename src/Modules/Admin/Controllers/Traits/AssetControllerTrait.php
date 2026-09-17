<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Controllers\Traits;

use Hirtz\Media\Models\Actions\DeleteAssets;
use Hirtz\Media\Models\Actions\ReorderAssets;
use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\Admin\Data\AssetArrayDataProvider;
use Hirtz\Media\Modules\Admin\Data\FileActiveDataProvider;
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
 * The bodies of the asset actions, without any opinion on who may run them. A controller serves one model, declares
 * its own access rules, resolves and authorises the record, and calls the matching method.
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
                'order' => ['post'],
                'remove' => ['post'],
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
     * @param Asset|null $asset an existing asset whose file the picked one replaces, instead of adding another asset
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

            if ($this->request->preferNoContent()) {
                $this->response->setStatusCode(204);
            }

            if (!$this->response->getIsOk() || !$file || $file->hasErrors()) {
                return $this->response;
            }

            if ($asset) {
                return $this->replaceAssetFile($asset, $file);
            }

            $this->insertAsset($model, $file);

            // An upload or import is triggered from the asset grid, which is only rendered by the index.
            return $isUploaded
                ? $this->renderIndex($model)
                : $this->redirectToAssetPicker($model, $folder, $q);
        }

        return $this->renderAssetPicker($model, $folder, $q, $asset);
    }

    /**
     * The picker's counterpart to adding a file: a model holds a file once, so the button of a file it already has
     * removes that asset instead of adding a second one.
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

        $this->errorOrSuccess($asset, Yii::t('media', 'ASSET_SUCCESS_DELETED'));

        return $this->redirectToAssetPicker($model, $folder, $q);
    }

    /**
     * The toggle leads back to the picker rather than out of it, and it redirects rather than rendering: everything
     * the picker contains — the folder dropdown, the search, the pager, the sort headers, the folder link of a row —
     * builds its links off the current request, so a rendered response would point all of them at the route the
     * button posted to, which for the POST-only `remove` is a 405. The filter travels with it, or a user narrowing
     * the library to a folder loses it on the first file they add.
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
        $asset = Yii::createObject($model->getAssetClass());
        $asset->loadDefaultValues();
        $asset->populateModelRelation($model);
        $asset->populateFileRelation($file);

        if (!$asset->insert()) {
            $this->error($asset);
            return;
        }

        $this->success($this->getAssetCreatedMessage($asset));
    }

    /**
     * The picker leads nowhere but back into itself, so the flash is the only way to the asset that was just
     * created — otherwise the assets tab is, and then finding it among the others. A `Message` rather than a
     * string because a flash encodes what it is handed and trusts only a `Stringable` (monorepo issue #160).
     */
    protected function getAssetCreatedMessage(Asset $asset): Stringable
    {
        $route = $asset->getAdminRoute();
        $name = $asset->file->getAdminName();

        $link = $route
            ? (string)A::make()->href(Url::to($route))->text($name)
            : Html::encode($name);

        return Message::make('media', 'ASSET_SUCCESS_CREATED', ['name' => $link]);
    }

    /**
     * The asset keeps everything but its file, which is the point of replacing it rather than creating a new one.
     */
    protected function replaceAssetFile(Asset $asset, File $file): Response
    {
        $asset->populateFileRelation($file);
        $asset->update();

        $this->errorOrSuccess($asset, Yii::t('media', 'ASSET_SUCCESS_FILE_REPLACED'));

        return $this->redirect(['update', 'id' => $asset->id]);
    }

    protected function updateAsset(Asset $asset): Response|string
    {
        if ($asset->load($this->request->post()) && !$this->request->isFormReload()) {
            if ($asset->update()) {
                $this->success(Yii::t('media', 'ASSET_SUCCESS_UPDATED'));
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
        $this->errorOrSuccess($asset, Yii::t('media', 'ASSET_SUCCESS_DELETED'));

        return $this->redirectToModel($asset);
    }

    /**
     * The selection is filtered through the model's own relation, so a posted id belonging to another record is
     * dropped rather than deleted — the route names the model and the controller has already authorised it.
     */
    protected function deleteAssets(AssetModelInterface $model): Response
    {
        $ids = array_map(intval(...), (array)$this->request->post('selection', []));
        $assets = $ids ? $model->getAssets()->andWhere(['id' => $ids])->all() : [];

        if ($assets) {
            $action = DeleteAssets::create($model, $assets);

            if ($count = count($action->getDeleted())) {
                $this->success(Yii::t('media', 'ASSET_SUCCESS_SELECTED_DELETED', ['count' => $count]));
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
            $this->success(Yii::t('media', 'ASSET_SUCCESS_ORDERED'));
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
            ...$asset::getAdminIndexRoute($asset->model),
            '#' => "asset-$asset->id",
        ]);
    }
}
