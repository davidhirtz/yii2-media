<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Controllers;

use Hirtz\Media\Models\Actions\ReorderFolder;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Models\Queries\FolderQuery;
use Hirtz\Media\Modules\Admin\Controllers\Traits\FolderControllerTrait;
use Hirtz\Media\Modules\Admin\Module;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Models\Forms\DeleteForm;
use Hirtz\Skeleton\Web\Controller;
use Hirtz\Skeleton\Widgets\Flashes;
use Override;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * @extends Controller<Module>
 */
class FolderController extends Controller
{
    use FolderControllerTrait;
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
                        'actions' => ['index', 'update'],
                        'roles' => [Folder::AUTH_FOLDER_UPDATE],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['create'],
                        'roles' => [Folder::AUTH_FOLDER_CREATE],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['delete'],
                        'roles' => [Folder::AUTH_FOLDER_DELETE],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['order'],
                        'roles' => [Folder::AUTH_FOLDER_ORDER],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                    'order' => ['post'],
                ],
            ],
        ];
    }

    public function actionIndex(?int $type = null, ?string $q = null): Response|string
    {
        $query = $this->getQuery()
            ->orderBy(static::getModule()->defaultFolderOrder)
            ->andFilterWhere(['type' => $type])
            ->matching($q);

        $provider = new ActiveDataProvider([
            'query' => $query,
            'sort' => false,
            'pagination' => false,
        ]);

        return $this->render('index', [
            'provider' => $provider,
        ]);
    }

    public function actionCreate(?int $type = null): Response|string
    {
        $folder = Folder::create();
        $folder->loadDefaultValues();
        $folder->type = $type;

        if (!$this->webuser->can(Folder::AUTH_FOLDER_CREATE, ['folder' => $folder])) {
            throw new ForbiddenHttpException();
        }

        if ($folder->load($this->request->post()) && $folder->insert()) {
            $this->success(Yii::t('media', 'FOLDER_SUCCESS_CREATED'));
            return $this->redirect(['index']);
        }

        return $this->render('create', [
            'folder' => $folder,
        ]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $folder = $this->findFolder($id, Folder::AUTH_FOLDER_UPDATE);

        if ($folder->load($this->request->post()) && $folder->update()) {
            $this->success(Yii::t('media', 'FOLDER_SUCCESS_UPDATED'));
            return $this->refresh();
        }

        return $this->render('update', [
            'folder' => $folder,
        ]);
    }

    public function actionDelete(int $id): Response|string
    {
        $folder = $this->findFolder($id, Folder::AUTH_FOLDER_DELETE);

        $form = DeleteForm::create([
            'model' => $folder,
            'attribute' => 'name',
        ]);

        if ($form->load($this->request->post()) && $form->delete()) {
            $this->success(Yii::t('media', 'FOLDER_SUCCESS_DELETED'));
        }

        $this->error($form);

        return $this->redirect(['index']);
    }

    public function actionOrder(): string
    {
        $success = ReorderFolder::runWithBodyParam('folder');

        if ($success) {
            $this->success(Yii::t('media', 'FOLDER_SUCCESS_ORDERED'));
        }

        return (string) Flashes::make();
    }

    protected function getQuery(): FolderQuery
    {
        return Folder::find();
    }
}
