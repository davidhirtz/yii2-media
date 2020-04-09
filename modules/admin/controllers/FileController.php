<?php

namespace davidhirtz\yii2\media\modules\admin\controllers;

use davidhirtz\yii2\media\modules\admin\data\FileActiveDataProvider;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\skeleton\web\Controller;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

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
                        'actions' => ['clone', 'create', 'index', 'update', 'delete'],
                        'roles' => ['upload'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'clone' => ['post'],
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
        /** @var FileActiveDataProvider $provider */
        $provider = Yii::createObject([
            'class' => 'davidhirtz\yii2\media\modules\admin\data\FileActiveDataProvider',
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

        $request = Yii::$app->getRequest();
        $file->copy($request->post('url')) || $file->upload();

        if ($file->upload && $file->upload->isPartial()) {
            return '';
        }

        if ($file->insert()) {
            if ($request->getIsAjax()) {
                return '';
            }

            $this->success(Yii::t('media', 'The file was created.'));
            return $this->redirect(['index', 'folder' => $file->folder_id]);
        }

        $errors = $file->getFirstErrors();
        throw new BadRequestHttpException(reset($errors));
    }

    /**
     * @param int $id
     * @return string|\yii\web\Response
     */
    public function actionUpdate($id)
    {
        $file = $this->findFile($id);

        if ($file->upload() || $file->load(Yii::$app->getRequest()->post())) {
            if ($file->update()) {
                $this->success(Yii::t('media', 'The file was updated.'));
                return !Yii::$app->getRequest()->getIsAjax() ? $this->refresh() : '';
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
    public function actionClone($id)
    {
        $file = $this->findFile($id);
        $clone = $file->clone();

        if ($errors = $clone->getFirstErrors()) {
            $this->error($errors);
        } else {
            $this->success(Yii::t('media', 'The file was duplicated.'));
        }

        return $this->redirect(['update', 'id' => $clone->id ?: $file->id]);
    }

    /**
     * @param int $id
     * @return string|\yii\web\Response
     */
    public function actionDelete($id)
    {
        $file = $this->findFile($id);

        if ($file->delete()) {
            if (Yii::$app->getRequest()->getIsAjax()) {
                return $this->asJson([]);
            }

            $this->success(Yii::t('media', 'The file was deleted.'));
            return $this->redirect(['index']);
        }

        $errors = $file->getFirstErrors();
        throw new BadRequestHttpException(reset($errors));
    }

    /**
     * @param int $id
     * @return File
     * @throws NotFoundHttpException
     */
    private function findFile($id)
    {
        if (!$entry = File::findOne((int)$id)) {
            throw new NotFoundHttpException;
        }

        return $entry;
    }
}