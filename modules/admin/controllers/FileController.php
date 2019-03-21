<?php

namespace davidhirtz\yii2\media\modules\admin\controllers;

use davidhirtz\yii2\media\modules\admin\models\forms\FolderForm;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\models\forms\FileForm;
use davidhirtz\yii2\skeleton\web\Controller;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Class BaseFileController.
 * @package davidhirtz\yii2\media\modules\admin\controllers
 * @see FileController
 *
 * @property \davidhirtz\yii2\media\modules\admin\Module $module
 */
class FileController extends Controller
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
                        'actions' => ['create', 'index', 'update', 'delete'],
                        'roles' => ['media'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'create' => ['post'],
                    'delete' => ['post'],
                ],
            ],
        ]);
    }

    /**
     * @param int $folder
     * @param int $type
     * @param string $q
     * @return string
     */
    public function actionIndex($folder = null, $type = null, $q = null)
    {
        $folder = $folder ? FolderForm::findOne($folder) : null;
        $query = $folder ? $folder->getFiles() : FileForm::find();

        $query->andFilterWhere(['type' => $type])
            ->matching($q);

        if (!$folder) {
            $query->with('folder');
        }

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'defaultPageSize' => 50,
            ],
            'sort' => [
                'defaultOrder' => ['updated_at' => SORT_DESC],
            ],
        ]);
        /** @noinspection MissedViewInspection */
        return $this->render('index', [
            'provider' => $provider,
            'file' => $folder,
        ]);
    }

    /**
     * @param int $folder
     * @return string|\yii\web\Response
     */
    public function actionCreate($folder = null)
    {
        $file = new FileForm;
        $file->folder_id = $folder;

        if ($file->insert()) {
            if (Yii::$app->getRequest()->getIsAjax()) {
                return '';
            }

            $this->success(Yii::t('media', 'The file was created.'));
            return $this->redirect(['index', 'folder' => $file->folder_id]);
        }

        $errors = $file->getFirstErrors();
        throw new ServerErrorHttpException(reset($errors));
    }

    /**
     * @param int $id
     * @return string|\yii\web\Response
     */
    public function actionUpdate($id)
    {
        if (!$file = FileForm::findOne($id)) {
            throw new NotFoundHttpException;
        }

        if ($file->load(Yii::$app->getRequest()->post()) && $file->update()) {
            $this->success(Yii::t('media', 'The file was updated.'));
            return $this->refresh();
        }

        /** @noinspection MissedViewInspection */
        return $this->render('update', [
            'file' => $file,
        ]);
    }

    /**
     * @param int $id
     * @return string|\yii\web\Response
     */
    public function actionDelete($id)
    {

        if (!$file = File::findOne($id)) {
            throw new NotFoundHttpException;
        }

        if ($file->delete()) {

            if (Yii::$app->getRequest()->getIsAjax()) {
                return $this->asJson([]);
            }

            $this->success(Yii::t('media', 'The file was deleted.'));
            return $this->redirect(['index']);
        }

        $errors = $file->getFirstErrors();
        throw new ServerErrorHttpException(reset($errors));
    }
}