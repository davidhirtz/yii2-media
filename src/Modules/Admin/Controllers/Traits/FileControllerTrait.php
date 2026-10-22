<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Controllers\Traits;

use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Web\ChunkedUploadedFile;
use Hirtz\Skeleton\Web\StreamUploadedFile;
use Hirtz\Skeleton\Web\Traits\UploadControllerTrait;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

trait FileControllerTrait
{
    use UploadControllerTrait;

    protected function findFile(int $id): File
    {
        if (!$file = File::findOne($id)) {
            throw new NotFoundHttpException();
        }

        return $file;
    }

    protected function insertFileFromRequest(?int $folderId = null): ?File
    {
        $folder = $folderId ? Folder::findOne($folderId) : FolderCollection::getDefault();

        if (!$folder) {
            throw new NotFoundHttpException();
        }

        if (!$this->webuser->can(File::AUTH_FILE)) {
            throw new ForbiddenHttpException();
        }

        $file = File::create();
        $file->loadDefaultValues();
        $file->populateFolderRelation($folder);
        $file->upload = $this->receiveUpload($file);
        $file->preview = $file->upload ? UploadedFile::getInstance($file, 'preview') : null;

        if (!$this->response->getIsOk()) {
            return null;
        }

        $file->upload ??= new StreamUploadedFile([
            'allowedExtensions' => $file->allowedExtensions,
            'url' => $this->request->post('url'),
        ]);

        $file->insert();

        // The uploader swaps a `200` into the page, so a refused upload must not answer one (#555).
        if ($file->upload instanceof ChunkedUploadedFile && $file->hasErrors()) {
            $errors = $file->getFirstErrors();
            $this->refuseUpload(400, (string)reset($errors));
            return null;
        }

        // The name, filename and extension are read from the upload, so their errors only repeat its own.
        $this->errorOrSuccess($file->hasErrors('upload') ? (string)$file->getFirstError('upload') : $file, Yii::t('media', 'FILE_CONTROLLER_SUCCESS_CREATED'));

        return !$file->hasErrors() ? $file : null;
    }
}
