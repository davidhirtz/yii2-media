<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Controllers;

use Hirtz\Media\Models\Actions\DuplicateFile;
use Hirtz\Media\Models\Actions\MoveFiles;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\Admin\Controllers\Traits\FileControllerTrait;
use Hirtz\Media\Modules\Admin\Data\FileActiveDataProvider;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Web\ChunkedUploadedFile;
use Hirtz\Skeleton\Web\Controller;
use Hirtz\Skeleton\Web\StreamUploadedFile;
use Override;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
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
                        'actions' => ['create', 'delete', 'duplicate', 'index', 'move-all', 'update'],
                        'roles' => [File::AUTH_FILE],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'create' => ['post'],
                    'delete' => ['post'],
                    'duplicate' => ['post'],
                    'move-all' => ['post'],
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
        $file = $this->findFile($id);

        // A form reload must not touch the upload branch, so it only loads and falls through to the render.
        if ($this->request->isFormReload()) {
            $file->load($this->request->post());
        } elseif ($this->request->getIsPost()) {
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
                $this->success(Yii::t('media', 'FILE_SUCCESS_UPDATED'));
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
        $file = $this->findFile($id);
        $duplicate = DuplicateFile::create(['file' => $file]);

        $this->errorOrSuccess($duplicate, Yii::t('media', 'FILE_SUCCESS_DUPLICATED'));
        return $this->redirect(['update', 'id' => $duplicate->id ?? $file->id]);
    }

    public function actionMoveAll(): Response|string
    {
        $target = Folder::findOne((int)$this->request->post('folder'));

        if (!$target) {
            throw new NotFoundHttpException();
        }

        $fileIds = array_map(intval(...), $this->request->post('selection', []));
        $files = $fileIds ? File::find()->andWhere(['id' => $fileIds])->all() : [];

        if ($files) {
            $action = MoveFiles::create($files, $target);

            if ($count = $action->getMovedCount()) {
                $this->success(Yii::t('media', 'FILE_SUCCESS_SELECTED_MOVED', [
                    'count' => $count,
                    'folder' => $target->name,
                ]));
            }

            if ($renamed = $action->getRenamed()) {
                $this->warning(Yii::t('media', 'FILE_WARNING_SELECTED_RENAMED', [
                    'count' => count($renamed),
                    'filenames' => implode(', ', array_map(fn (File $file): string => $file->getFilename(), $renamed)),
                ]));
            }

            foreach ($action->getFailed() as $file) {
                $this->error($file);
            }
        }

        return $this->redirect($this->request->getReferrer() ?? ['index']);
    }

    public function actionDelete(int $id): Response|string
    {
        $file = $this->findFile($id);

        $file->delete();
        $this->errorOrSuccess($file, Yii::t('media', 'FILE_SUCCESS_DELETED'));

        return $this->redirect([
            'index',
            ...$this->request->getQueryParams(),
            'id' => null,
        ]);
    }
}
