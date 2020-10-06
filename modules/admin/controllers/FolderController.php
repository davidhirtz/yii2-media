<?php

namespace davidhirtz\yii2\media\modules\admin\controllers;

use davidhirtz\yii2\media\modules\admin\controllers\traits\FolderTrait;
use davidhirtz\yii2\media\modules\admin\Module;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\models\queries\FolderQuery;
use davidhirtz\yii2\skeleton\web\Controller;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Class BaseFolderController.
 * @package davidhirtz\yii2\media\modules\admin\controllers
 * @see FolderController
 *
 * @property Module $module
 */
class FolderController extends Controller
{
    use FolderTrait;
    use ModuleTrait;

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['index', 'update'],
                        'roles' => ['folderUpdate'],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['create'],
                        'roles' => ['folderCreate'],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['delete'],
                        'roles' => ['folderDelete'],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['order'],
                        'roles' => ['folderOrder'],
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
        ]);
    }

    /**
     * @param int|null $id
     * @param int|null $type
     * @param string|null $q
     * @return string
     */
    public function actionIndex($id = null, $type = null, $q = null)
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

        /** @noinspection MissedViewInspection */
        return $this->render('index', [
            'provider' => $provider,
            'folder' => $folder,
        ]);
    }

    /**
     * @param int|null $id
     * @param int|null $type
     * @return string|Response
     */
    public function actionCreate($id = null, $type = null)
    {
        $folder = new Folder();
        $folder->parent_id = $id;
        $folder->type = $type;

        if (!Yii::$app->getUser()->can('folderCreate', ['folder' => $folder])) {
            throw new ForbiddenHttpException();
        }

        if ($folder->load(Yii::$app->getRequest()->post()) && $folder->insert()) {
            $this->success(Yii::t('media', 'The folder was created.'));
            return $this->redirect(['index']);
        }

        /** @noinspection MissedViewInspection */
        return $this->render('create', [
            'folder' => $folder,
        ]);
    }

    /**
     * @param int $id
     * @return string|Response
     */
    public function actionUpdate(int $id)
    {
        $folder = $this->findFolder($id, 'folderUpdate');

        if ($folder->load(Yii::$app->getRequest()->post()) && $folder->update()) {
            $this->success(Yii::t('media', 'The folder was updated.'));
            return $this->refresh();
        }

        /** @noinspection MissedViewInspection */
        return $this->render('update', [
            'folder' => $folder,
        ]);
    }

    /**
     * @param int $id
     * @return string|Response
     */
    public function actionDelete($id)
    {
        $folder = $this->findFolder($id, 'folderDelete');

        if ($folder->delete()) {
            $this->success(Yii::t('media', 'The folder was deleted.'));
            return $this->redirect(['index']);
        }

        $errors = $folder->getFirstErrors();
        throw new ServerErrorHttpException(reset($errors));
    }

    /**
     * @param int|null $id
     */
    public function actionOrder($id = null)
    {
        $folders = Folder::find()->select(['id', 'position'])
            ->andWhere(['parent_id' => $id])
            ->orderBy(['position' => SORT_ASC])
            ->all();

        Folder::updatePosition($folders, array_flip(Yii::$app->getRequest()->post('folder')));
    }

    /**
     * @return FolderQuery
     */
    protected function getQuery()
    {
        return Folder::find()->replaceI18nAttributes();
    }
}