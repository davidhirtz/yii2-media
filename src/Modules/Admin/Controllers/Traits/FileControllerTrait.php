<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Controllers\Traits;

use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Web\StreamUploadedFile;
use Hirtz\Skeleton\Web\Traits\UploadControllerTrait;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

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
        $file->upload = $this->receiveUpload($file);

        if (!$this->response->getIsOk()) {
            return null;
        }

        $file->upload ??= new StreamUploadedFile([
            'allowedExtensions' => $file->allowedExtensions,
            'url' => $this->request->post('url'),
        ]);

        $file->insert();
        $this->errorOrSuccess($file, Yii::t('media', 'FILE_CONTROLLER_SUCCESS_CREATED'));

        return !$file->hasErrors() ? $file : null;
    }
}
