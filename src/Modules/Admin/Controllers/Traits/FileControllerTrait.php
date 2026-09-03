<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Controllers\Traits;

use Hirtz\Skeleton\I18n\Lang;
use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Web\ChunkedUploadedFile;
use Hirtz\Skeleton\Web\StreamUploadedFile;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

trait FileControllerTrait
{
    protected function findFile(int $id, ?string $permissionName = null): File
    {
        if (!$file = File::findOne($id)) {
            throw new NotFoundHttpException();
        }

        if ($permissionName && !Yii::$app->getUser()->can($permissionName, ['file' => $file])) {
            throw new ForbiddenHttpException();
        }

        return $file;
    }

    protected function insertFileFromRequest(?int $folderId = null): ?File
    {
        $folder = $folderId ? Folder::findOne($folderId) : FolderCollection::getDefault();

        if (!$folder) {
            throw new NotFoundHttpException();
        }

        if (!Yii::$app->getUser()->can(File::AUTH_FILE_CREATE, ['folder' => $folder])) {
            throw new ForbiddenHttpException();
        }

        $file = File::create();
        $file->loadDefaultValues();
        $file->upload = ChunkedUploadedFile::getInstance($file, 'upload');

        if ($file->upload?->isPartial()) {
            $this->response->setStatusCode(201);
            return null;
        }

        $file->upload ??= new StreamUploadedFile([
            'allowedExtensions' => $file->allowedExtensions,
            'url' => $this->request->post('url'),
        ]);

        $file->insert();
        $this->errorOrSuccess($file, Lang::t('media', 'FILE_CONTROLLER_FLASH_THE_FILE_WAS_CREATED'));

        return !$file->hasErrors() ? $file : null;
    }
}
