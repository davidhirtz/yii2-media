<?php

namespace davidhirtz\yii2\media\modules\admin\controllers\traits;

use davidhirtz\yii2\media\models\Folder;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Trait FolderTrait
 * @package davidhirtz\yii2\media\modules\admin\controllers\traits
 */
trait FolderTrait
{
    /**
     * @param int $id
     * @param string|null $permissionName
     * @return Folder
     */
    protected function findFolder($id, $permissionName = null)
    {
        if (!$folder = Folder::findOne((int)$id)) {
            throw new NotFoundHttpException();
        }

        if ($permissionName && !Yii::$app->getUser()->can($permissionName, ['folder' => $folder])) {
            throw new ForbiddenHttpException();
        }

        return $folder;
    }
}
