<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Controllers;

use Hirtz\Media\Models\Actions\DeleteAssets;
use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Controllers\Traits\FileControllerTrait;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Media\Modules\Admin\Module;
use Hirtz\Skeleton\Web\Controller;
use Override;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * The assets of one file, across every subclass. Removing one here stays here — the asset's own controller would
 * redirect to the record it belongs to, which is not where the user was.
 */
/**
 * @extends Controller<Module>
 */
class AssetController extends Controller
{
    use FileControllerTrait;
    use ModuleTrait;

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
                        'actions' => ['delete', 'delete-all', 'index'],
                        'roles' => [File::AUTH_FILE],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                    'delete-all' => ['post'],
                ],
            ],
        ];
    }

    public function actionIndex(?int $file = null): Response|string
    {
        return $this->render('index', [
            'file' => $this->findFileWithAssets($file),
        ]);
    }

    public function actionDelete(int $id): Response|string
    {
        $asset = $this->findAsset($id);
        $file = $asset->file;

        $asset->delete();
        $this->errorOrSuccess($asset, Yii::t('media', 'ASSET_SUCCESS_DELETED'));

        return $this->redirect($file->asset_count
            ? ['index', 'file' => $file->id]
            : ['/admin/media/file/update', 'id' => $file->id]);
    }

    /**
     * The selection spans the records using the file, so each record's assets are deleted together and it is recounted
     * once. A posted id of another file, or of an asset the user may not manage, is dropped.
     */
    public function actionDeleteAll(int $file): Response
    {
        $file = $this->findFileWithAssets($file);
        $ids = array_map(intval(...), (array)$this->request->post('selection', []));

        $assets = $ids ? Asset::find()->where(['file_id' => $file->id, 'id' => $ids])->all() : [];
        Asset::populateModelRelations($assets);

        $groups = [];
        $count = 0;

        foreach ($assets as $asset) {
            if (!$this->webuser->can($asset->getPermissionName())) {
                continue;
            }

            $asset->populateFileRelation($file);

            // Populated only for a record that still exists: `model_id` has no foreign key.
            if ($asset->isRelationPopulated('model')) {
                $groups[spl_object_id($asset->model)][] = $asset;
            } elseif ($asset->delete()) {
                $count++;
            }
        }

        foreach ($groups as $group) {
            $action = DeleteAssets::create($group[0]->model, $group);
            $count += count($action->getDeleted());

            foreach ($action->getFailed() as $asset) {
                $this->error($asset);
            }
        }

        if ($count) {
            $this->success(Yii::t('media', 'ASSET_SUCCESS_SELECTED_DELETED', ['count' => $count]));
        }

        $file->refresh();

        return $this->redirect($file->asset_count
            ? ['index', 'file' => $file->id]
            : ['/admin/media/file/update', 'id' => $file->id]);
    }

    protected function findFileWithAssets(?int $id): File
    {
        if (!$id) {
            throw new NotFoundHttpException();
        }

        return $this->findFile($id);
    }

    /**
     * Removing an asset is the asset's business, whichever model it belongs to, so its own permission decides.
     */
    protected function findAsset(int $id): Asset
    {
        $asset = Asset::findOne($id);

        if (!$asset) {
            throw new NotFoundHttpException();
        }

        if (!$this->webuser->can($asset->getPermissionName())) {
            throw new ForbiddenHttpException();
        }

        return $asset;
    }
}
