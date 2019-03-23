<?php

namespace davidhirtz\yii2\media\modules\admin\controllers;

use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\models\queries\FolderQuery;
use davidhirtz\yii2\media\modules\admin\models\forms\FolderForm;
use davidhirtz\yii2\skeleton\web\Controller;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;

/**
 * Class BaseFolderController.
 * @package davidhirtz\yii2\media\modules\admin\controllers
 * @see FolderController
 *
 * @property \davidhirtz\yii2\media\modules\admin\Module $module
 */
class FolderController extends Controller
{
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
                        'actions' => ['create', 'index', 'order', 'update', 'upload', 'delete'],
                        'roles' => ['upload'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                    'order' => ['post'],
                    'upload' => ['post'],
                ],
            ],
        ]);
    }

    /**
     * @param int $id
     * @param int $type
     * @param string $q
     * @return string
     */
    public function actionIndex($id = null, $type = null, $q = null)
    {
        $folder = $id ? FolderForm::findOne($id) : null;

        $query = $this->getQuery()
            ->orderBy(['position' => SORT_ASC])
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
     * @param int $id
     * @param int $type
     * @return string|\yii\web\Response
     */
    public function actionCreate($id = null, $type = null)
    {
        $folder = new FolderForm;

        $folder->parent_id = $id;
        $folder->type = $type;

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
     * @return string|\yii\web\Response
     */
    public function actionUpdate($id)
    {
        if (!$folder = FolderForm::findOne($id)) {
            throw new NotFoundHttpException;
        }

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
     * @return string|\yii\web\Response
     */
    public function actionDelete($id)
    {
        if (!$folder = Folder::findOne($id)) {
            throw new NotFoundHttpException;
        }

        if ($folder->delete()) {
            $this->success(Yii::t('media', 'The folder was deleted.'));
            return $this->redirect(['index']);
        }

        $errors = $folder->getFirstErrors();
        throw new ServerErrorHttpException(reset($errors));
    }

    /**
     * @param int $id
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
        return FolderForm::find()->replaceI18nAttributes();
    }
}