<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Controllers\Traits;

use Hirtz\Media\Models\Folder;
use yii\web\NotFoundHttpException;

trait FolderControllerTrait
{
    protected function findFolder(int $id): Folder
    {
        if (!$folder = Folder::findOne($id)) {
            throw new NotFoundHttpException();
        }

        return $folder;
    }
}
