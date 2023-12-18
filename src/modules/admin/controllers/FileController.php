<?php

namespace davidhirtz\yii2\media\modules\admin\controllers;

use davidhirtz\yii2\media\models\actions\DuplicateFile;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\admin\controllers\traits\FileTrait;
use davidhirtz\yii2\media\modules\admin\data\FileActiveDataProvider;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\web\Controller;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class FileController extends Controller
{
    use FileTrait;
    use ModuleTrait;

    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['index', 'update'],
                        'roles' => [File::AUTH_FILE_UPDATE],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['duplicate', 'create'],
                        'roles' => [File::AUTH_FILE_CREATE],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['delete'],
                        'roles' => [File::AUTH_FILE_DELETE],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'create' => ['post'],
                    'delete' => ['post'],
                    'duplicate' => ['post'],
                ],
            ],
        ]);
    }

    public function actionIndex(?int $folder = null, ?int $type = null, ?string $q = null): Response|string
    {
        $provider = Yii::$container->get(FileActiveDataProvider::class, [], [
            'folder' => Folder::findOne($folder),
            'type' => $type,
            'search' => $q,
        ]);

        return $this->render('index', [
            'provider' => $provider,
        ]);
    }

    public function actionCreate(?int $folder = null): Response|string
    {
        if (!($file = $this->insertFileFromRequest($folder)) || Yii::$app->getRequest()->getIsAjax()) {
            return '';
        }

        $this->success(Yii::t('media', 'The file was created.'));
        return $this->redirect(['index', 'folder' => $file->folder_id]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $file = $this->findFile($id, File::AUTH_FILE_UPDATE);

        $request = Yii::$app->getRequest();
        $isUpload = ($url = $request->post('url')) ? $file->copy($url) : $file->upload();

        if ($isUpload) {
            if (!Yii::$app->getUser()->can(File::AUTH_FILE_CREATE, ['folder' => $file->folder])) {
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

        return $this->render('update', [
            'file' => $file,
        ]);
    }

    public function actionDuplicate(int $id): Response|string
    {
        $file = $this->findFile($id, File::AUTH_FILE_UPDATE);

        $duplicate = DuplicateFile::create([
            'file' => $file,
        ]);

        if ($errors = $duplicate->getFirstErrors()) {
            $this->error($errors);
        } else {
            $this->success(Yii::t('media', 'The file was duplicated.'));
        }

        return $this->redirect(['update', 'id' => $duplicate->id ?? $file->id]);
    }

    public function actionDelete(int $id): Response|string
    {
        $file = $this->findFile($id, File::AUTH_FILE_DELETE);

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
