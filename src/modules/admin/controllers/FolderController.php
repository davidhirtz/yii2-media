<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\controllers;

use davidhirtz\yii2\media\models\actions\ReorderFolder;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\models\queries\FolderQuery;
use davidhirtz\yii2\media\modules\admin\controllers\traits\FolderTrait;
use davidhirtz\yii2\media\modules\admin\Module;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\web\Controller;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * @property Module $module
 */
class FolderController extends Controller
{
    use FolderTrait;
    use ModuleTrait;

    #[\Override]
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

    public function actionIndex(?int $id = null, ?int $type = null, ?string $q = null): Response|string
    {
        $folder = $id ? Folder::findOne($id) : null;

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
            'folder' => $folder,
        ]);
    }

    public function actionCreate(?int $type = null): Response|string
    {
        $folder = Folder::create();
        $folder->loadDefaultValues();
        $folder->type = $type;

        if (!Yii::$app->getUser()->can(Folder::AUTH_FOLDER_CREATE, ['folder' => $folder])) {
            throw new ForbiddenHttpException();
        }

        if ($folder->load(Yii::$app->getRequest()->post()) && $folder->insert()) {
            $this->success(Yii::t('media', 'The folder was created.'));
            return $this->redirect(['index']);
        }

        return $this->render('create', [
            'folder' => $folder,
        ]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $folder = $this->findFolder($id, Folder::AUTH_FOLDER_UPDATE);

        if ($folder->load(Yii::$app->getRequest()->post()) && $folder->update()) {
            $this->success(Yii::t('media', 'The folder was updated.'));
            return $this->refresh();
        }

        return $this->render('update', [
            'folder' => $folder,
        ]);
    }

    public function actionDelete(int $id): Response|string
    {
        $folder = $this->findFolder($id, Folder::AUTH_FOLDER_DELETE);

        if ($folder->delete()) {
            $this->success(Yii::t('media', 'The folder was deleted.'));
            return $this->redirect(['index']);
        }

        $errors = $folder->getFirstErrors();
        throw new ServerErrorHttpException(reset($errors));
    }

    public function actionOrder(): void
    {
        ReorderFolder::runWithBodyParam('folder');
    }

    protected function getQuery(): FolderQuery
    {
        return Folder::find();
    }
}
