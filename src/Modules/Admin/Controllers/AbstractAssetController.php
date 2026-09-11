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
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Serves the asset subclasses of `$assetClasses`, whatever model they belong to: the query string names the model
 * through its own param name, and the permissions come from the subclass.
 */
abstract class AbstractAssetController extends Controller
{
    use FileControllerTrait;
    use ModuleTrait;

    /**
     * @var list<class-string<Asset>>
     */
    protected array $assetClasses = [];

    #[Override]
    public function behaviors(): array
    {
        return [
            ...parent::behaviors(),
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['index', 'update'],
                        'roles' => $this->getPermissionNames('update'),
                    ],
                    [
                        'allow' => true,
                        'actions' => ['create', 'duplicate'],
                        'roles' => $this->getPermissionNames('create'),
                    ],
                    [
                        'allow' => true,
                        'actions' => ['delete'],
                        'roles' => $this->getPermissionNames('delete'),
                    ],
                    [
                        'allow' => true,
                        'actions' => ['order'],
                        'roles' => $this->getPermissionNames('order'),
                    ],
                ],
            ],
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

    public function actionIndex(): Response|string
    {
        $model = $this->findModel('update');

        $provider = Yii::$container->get(AssetArrayDataProvider::class, config: [
            'model' => $model,
        ]);

        return $this->render('index', [
            'model' => $model,
            'provider' => $provider,
        ]);
    }

    public function actionCreate(?int $file = null, ?int $folder = null, ?string $q = null): Response|string
    {
        $model = $this->findModel('create');

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

    public function actionUpdate(int $id): Response|string
    {
        $asset = $this->findAsset($id, 'update');

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

    public function actionDelete(int $id): Response|string
    {
        $asset = $this->findAsset($id, 'delete');

        $asset->delete();
        $this->errorOrSuccess($asset, Lang::t('media', 'ASSET_SUCCESS_DELETED'));

        return $this->redirectToModel($asset);
    }

    public function actionDuplicate(int $id): Response|string
    {
        $asset = $this->findAsset($id, 'create');

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

    public function actionOrder(): string
    {
        $model = $this->findModel('order');

        $success = ReorderAssets::runWithBodyParam('asset', [
            'model' => $model,
        ]);

        if ($success) {
            $this->success(Lang::t('media', 'ASSET_SUCCESS_ORDERED'));
        }

        return (string)Flashes::make();
    }

    /**
     * @param 'create'|'delete'|'order'|'update' $action
     */
    protected function findModel(string $action): AssetModelInterface
    {
        foreach ($this->assetClasses as $assetClass) {
            $modelClass = $assetClass::getModelClass();
            $paramName = $modelClass::instance()->getParamName();
            $id = $this->request->getQueryParam($paramName);

            if ($id === null) {
                continue;
            }

            $model = Yii::createObject($modelClass)::findOne((int)$id);

            if (!$model instanceof AssetModelInterface || !$model->hasAssetsEnabled()) {
                throw new NotFoundHttpException();
            }

            // Populated first: a subclass may delegate the permission to the model it belongs to.
            $asset = Yii::createObject($assetClass);
            $asset->populateModelRelation($model);

            if (!Yii::$app->getUser()->can($asset->getPermissionName($action), [$paramName => $model])) {
                throw new ForbiddenHttpException();
            }

            return $model;
        }

        throw new NotFoundHttpException();
    }

    /**
     * @param 'create'|'delete'|'order'|'update' $action
     */
    protected function findAsset(int $id, string $action): Asset
    {
        $asset = Asset::findOne($id);

        if (!$asset || !in_array($asset::class, $this->assetClasses, true)) {
            throw new NotFoundHttpException();
        }

        $model = $asset->model;
        $paramName = $model->getParamName();

        if (!Yii::$app->getUser()->can($asset->getPermissionName($action), [
            'asset' => $asset,
            $paramName => $model,
        ])) {
            throw new ForbiddenHttpException();
        }

        return $asset;
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

    /**
     * @param 'create'|'delete'|'order'|'update' $action
     * @return list<string>
     */
    protected function getPermissionNames(string $action): array
    {
        $names = array_map(
            fn (string $assetClass): string => $assetClass::instance()->getPermissionName($action),
            $this->assetClasses
        );

        return array_values(array_unique($names));
    }
}
