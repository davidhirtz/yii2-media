<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\controllers;

use davidhirtz\yii2\media\models\actions\DuplicateFile;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\admin\controllers\traits\FileControllerTrait;
use davidhirtz\yii2\media\modules\admin\data\FileActiveDataProvider;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\web\ChunkedUploadedFile;
use davidhirtz\yii2\skeleton\web\Controller;
use davidhirtz\yii2\skeleton\web\StreamUploadedFile;
use Override;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Response;

class FileController extends Controller
{
    use FileControllerTrait;
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

    public function actionCreate(?int $folder = null): Response
    {
        $file = $this->insertFileFromRequest($folder);

        if (!$file) {
            return $this->response;
        }

        $this->success(Yii::t('media', 'The file was created.'));
        return $this->redirect(['index', 'folder' => $folder]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $file = $this->findFile($id, File::AUTH_FILE_UPDATE);

        if ($this->request->getIsPost()) {
            $file->upload = ChunkedUploadedFile::getInstance($file, 'upload');

            if ($file->upload?->isPartial()) {
                return $this->response->setStatusCode(201);
            }

            if ($file->upload === null) {
                $url = $this->request->post('url');

                if ($url) {
                    $file->upload = new StreamUploadedFile([
                        'allowedExtensions' => $file->allowedExtensions,
                        'url' => $url,
                    ]);
                }
            }

            $file->load($this->request->post());

            if ($file->update()) {
                $this->success(Yii::t('media', 'The file was updated.'));
                return $this->refresh();
            }

            if ($file->upload) {
                $errors = $file->getFirstErrors();
                return $errors ? $this->response->setStatusCode(400, reset($errors)) : $this->response;
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

        $file->delete();
        $this->errorOrSuccess($file, Yii::t('media', 'The file was deleted.'));

        return $this->redirect([
            'index',
            ...$this->request->getQueryParams(),
            'id' => null,
        ]);
    }
}
