<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Controllers;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Override;
use Yii;
use yii\filters\AccessControl;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Serves every registered subclass that did not point itself at a controller of its own. The permissions cannot be
 * listed up front — they belong to whichever subclass the query string names — so the access rules only demand a
 * signed-in user and every action checks the subclass's own permission against the record it resolved.
 */
class AssetController extends AbstractAssetController
{
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
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    #[Override]
    public function actionIndex(): Response|string
    {
        return $this->renderIndex($this->findModelForAction('update'));
    }

    #[Override]
    public function actionCreate(?int $file = null, ?int $folder = null, ?string $q = null): Response|string
    {
        return $this->createAsset($this->findModelForAction('create'), $file, $folder, $q);
    }

    #[Override]
    public function actionUpdate(int $id): Response|string
    {
        return $this->updateAsset($this->findAssetForAction($id, 'update'));
    }

    #[Override]
    public function actionDelete(int $id): Response|string
    {
        return $this->deleteAsset($this->findAssetForAction($id, 'delete'));
    }

    #[Override]
    public function actionDuplicate(int $id): Response|string
    {
        return $this->duplicateAsset($this->findAssetForAction($id, 'create'));
    }

    #[Override]
    public function actionOrder(): string
    {
        return $this->reorderAssets($this->findModelForAction('order'));
    }

    /**
     * @return list<class-string<Asset>> the subclasses that kept the default admin route
     */
    protected function getAssetClasses(): array
    {
        return array_values(array_filter(
            static::getModule()->getAssetClasses(),
            fn (string $assetClass): bool => $assetClass::getAdminControllerRoute() === Asset::getAdminControllerRoute()
        ));
    }

    /**
     * @param 'create'|'delete'|'order'|'update' $action
     */
    protected function findModelForAction(string $action): AssetModelInterface
    {
        foreach ($this->getAssetClasses() as $assetClass) {
            $modelClass = $assetClass::getModelClass();
            $paramName = $modelClass::instance()->getParamName();
            $id = $this->request->getQueryParam($paramName);

            if ($id === null) {
                continue;
            }

            $model = $this->findAssetModel(Yii::createObject($modelClass)::findOne((int)$id));

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
    protected function findAssetForAction(int $id, string $action): Asset
    {
        $asset = $this->findAsset($id, ...$this->getAssetClasses());
        $model = $asset->model;

        if (!Yii::$app->getUser()->can($asset->getPermissionName($action), [
            'asset' => $asset,
            $model->getParamName() => $model,
        ])) {
            throw new ForbiddenHttpException();
        }

        return $asset;
    }
}
