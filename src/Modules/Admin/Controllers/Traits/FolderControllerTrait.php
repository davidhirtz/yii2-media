<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Controllers\Traits;

use Hirtz\Media\Models\Folder;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

trait FolderControllerTrait
{
    protected function findFolder(int $id, ?string $permissionName = null): Folder
    {
        if (!$folder = Folder::findOne($id)) {
            throw new NotFoundHttpException();
        }

        if ($permissionName && !$this->webuser->can($permissionName, ['folder' => $folder])) {
            throw new ForbiddenHttpException();
        }

        return $folder;
    }
}
