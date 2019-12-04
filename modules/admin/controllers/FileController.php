<?php

namespace davidhirtz\yii2\media\modules\admin\controllers;

use davidhirtz\yii2\media\modules\admin\data\FileActiveDataProvider;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\skeleton\web\Controller;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
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
                        'roles' => ['upload'],
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
        $provider = new FileActiveDataProvider([
            'folderId' => $folder,
            'type' => $type,
            'search' => $q,
        ]);

        /** @noinspection MissedViewInspection */
        return $this->render('index', [
            'provider' => $provider,
        ]);
    }

    /**
     * @param int $folder
     * @return string|\yii\web\Response
     */
    public function actionCreate($folder = null)
    {
        $file = new File;
        $file->folder_id = $folder;

        if (!$file->upload()) {
            return '';
        }

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
        if (!$file = File::findOne($id)) {
            throw new NotFoundHttpException;
        }

        if ($file->upload() || $file->load(Yii::$app->getRequest()->post())) {
            if ($file->update()) {
                if (Yii::$app->getRequest()->getIsAjax()) {
                    return '';
                }

                $this->success(Yii::t('media', 'The file was updated.'));
                return $this->refresh();
            }
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