<?php

namespace davidhirtz\yii2\media\modules\admin\controllers;

use davidhirtz\yii2\media\modules\admin\controllers\traits\FileTrait;
use davidhirtz\yii2\media\modules\admin\data\FileActiveDataProvider;
use davidhirtz\yii2\media\modules\admin\Module;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\web\Controller;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Class FileController
 * @package davidhirtz\yii2\media\modules\admin\controllers
 *
 * @property Module $module
 */
class FileController extends Controller
{
    use FileTrait;
    use ModuleTrait;

    /**
     * @inheritDoc
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
                        'roles' => ['fileUpdate'],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['clone', 'create'],
                        'roles' => ['fileCreate'],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['delete'],
                        'roles' => ['fileDelete'],
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
     * @param int|null $folder
     * @param int|null $type
     * @param string|null $q
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
     * @param int|null $folder
     * @return string|Response
     */
    public function actionCreate($folder = null)
    {
        if (!($file = $this->insertFileFromRequest($folder)) || Yii::$app->getRequest()->getIsAjax()) {
            return '';
        }

        $this->success(Yii::t('media', 'The file was created.'));
        return $this->redirect(['index', 'folder' => $file->folder_id]);
    }

    /**
     * @param int $id
     * @return string|Response
     */
    public function actionUpdate($id)
    {
        $file = $this->findFile($id, 'fileUpdate');

        $request = Yii::$app->getRequest();
        $isUpload = ($url = $request->post('url')) ? $file->copy($url) : $file->upload();

        if ($isUpload) {
            if (!Yii::$app->getUser()->can('fileCreate', ['folder' => $file->folder])) {
                throw new ForbiddenHttpException();
            }
        }

        if ($isUpload || $file->load(Yii::$app->getRequest()->post())) {
            // Update could return `false` if nothing was updated, better also check for errors
            $isUpdated = $file->update();

            if (!$file->hasErrors()) {
                if (!$request->getIsAjax()) {
                    if ($isUpdated) {
                        $this->success(Yii::t('media', 'The file was updated.'));
                    }

                    return $this->refresh();
                }

                return '';
            }
        }

        /** @noinspection MissedViewInspection */
        return $this->render('update', [
            'file' => $file,
        ]);
    }

    /**
     * @param int $id
     * @return string|Response
     */
    public function actionClone($id)
    {
        $file = $this->findFile($id, 'fileUpdate');
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
     * @return string|Response
     */
    public function actionDelete($id)
    {
        $file = $this->findFile($id, 'fileDelete');

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
}