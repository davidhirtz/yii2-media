<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\controllers\traits;

use davidhirtz\yii2\media\models\File;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

trait FileTrait
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

    /**
     * This is not very elegant right now. But copy errors need to be handled by validation and upload errors might be
     * a partial upload that should simply end the request.
     */
    protected function insertFileFromRequest(?int $folderId = null): ?File
    {
        $file = File::create();
        $file->loadDefaultValues();
        $file->folder_id = $folderId;

        if (!Yii::$app->getUser()->can(File::AUTH_FILE_CREATE, ['file' => $file])) {
            throw new ForbiddenHttpException();
        }

        if ($url = Yii::$app->getRequest()->post('url')) {
            $file->copy($url);
        } elseif (!$file->upload()) {
            return null;
        }

        if (!$file->insert()) {
            $errors = $file->getFirstErrors();
            throw new BadRequestHttpException(reset($errors));
        }

        return $file;
    }
}
