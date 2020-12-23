<?php

namespace davidhirtz\yii2\media\modules\admin\controllers\traits;

use davidhirtz\yii2\media\models\File;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Trait FileTrait
 * @package davidhirtz\yii2\media\modules\admin\controllers\traits
 */
trait FileTrait
{
    /**
     * @param int $id
     * @param string|null $permissionName
     * @return File
     */
    protected function findFile($id, $permissionName = null)
    {
        if (!$file = File::findOne((int)$id)) {
            throw new NotFoundHttpException();
        }

        if ($permissionName && !Yii::$app->getUser()->can($permissionName, ['file' => $file])) {
            throw new ForbiddenHttpException();
        }

        return $file;
    }
}