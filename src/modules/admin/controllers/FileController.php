<?php

declare(strict_types=1);

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
        ];
    }

    public function actionIndex(?int $folder = null, ?string $q = null): Response|string
    {
        $provider = Yii::$container->get(FileActiveDataProvider::class, [], [
            'folder' => Folder::findOne($folder),
            'search' => $q,
        ]);

        return $this->render('index', [
            'provider' => $provider,
        ]);
    }

    public function actionCreate(?int $folder = null): Response|string
    {
        $file = $this->insertFileFromRequest($folder);

        if (!$file || Yii::$app->getRequest()->getIsAjax()) {
            return '';
        }

        $this->success(Yii::t('media', 'The file was created.'));
        return $this->redirect(['index', 'folder' => $file->folder_id]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $file = $this->findFile($id, File::AUTH_FILE_UPDATE);

        $request = Yii::$app->getRequest();
        $url = $request->post('url');

        if ($url) {
            $file->copy($url);
        }

        $isUpload = $url || $file->upload();

        if ($isUpload) {
            if (!Yii::$app->getUser()->can(File::AUTH_FILE_CREATE, ['folder' => $file->folder])) {
                $file->deleteTemporaryUpload();
                throw new ForbiddenHttpException();
            }
        }

        if ($isUpload || $file->load(Yii::$app->getRequest()->post())) {
            $isUpdated = $file->update();

            if ($request->getIsAjax()) {
                $errors = $file->getFirstErrors();
                if ($errors) {
                    throw new BadRequestHttpException(reset($errors));
                }

                return '';
            }

            if (!$file->hasErrors()) {
                if ($isUpdated) {
                    $this->success(Yii::t('media', 'The file was updated.'));
                }

                return $this->refresh();
            }
        }

        return $this->render('update', [
            'file' => $file,
        ]);
    }

    public function actionDuplicate(int $id): Response|string
    {
        $file = $this->findFile($id, File::AUTH_FILE_UPDATE);
        $duplicate = DuplicateFile::create(['file' => $file]);

        $this->errorOrSuccess($duplicate, Yii::t('media', 'The file was duplicated.'));

        return $this->redirect(['update', 'id' => $duplicate->id ?? $file->id]);
    }

    public function actionDelete(int $id): Response|string
    {
        $file = $this->findFile($id, File::AUTH_FILE_DELETE);

        if ($file->delete() && Yii::$app->getRequest()->getIsAjax()) {
            return $this->asJson([]);
        }

        $this->errorOrSuccess($file, Yii::t('media', 'The file was deleted.'));
        return $this->redirect(['index']);
    }
}
