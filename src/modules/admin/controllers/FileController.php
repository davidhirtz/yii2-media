<?php

declare(strict_types=1);

namespace Hirtz\Media\modules\admin\controllers;

use Hirtz\Media\models\actions\DuplicateFile;
use Hirtz\Media\models\File;
use Hirtz\Media\models\Folder;
use Hirtz\Media\modules\admin\controllers\traits\FileControllerTrait;
use Hirtz\Media\modules\admin\data\FileActiveDataProvider;
use Hirtz\Media\modules\ModuleTrait;
use Hirtz\Skeleton\web\ChunkedUploadedFile;
use Hirtz\Skeleton\web\Controller;
use Hirtz\Skeleton\web\StreamUploadedFile;
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

    public function actionIndex(?int $status = null, ?int $folder = null, ?string $q = null): Response|string
    {
        $provider = Yii::$container->get(FileActiveDataProvider::class, config: [
            'folder' => Folder::findOne($folder),
            'status' => $status,
            'search' => $q,
        ]);

        return $this->render('index', [
            'provider' => $provider,
        ]);
    }

    public function actionCreate(?int $folder = null): Response
    {
        $this->insertFileFromRequest($folder);

        if ($this->request->preferNoContent()) {
            $this->response->setStatusCode(204);
        }

        if (!$this->response->getIsOk()) {
            return $this->response;
        }

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
