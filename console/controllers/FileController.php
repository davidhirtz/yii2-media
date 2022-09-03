<?php

namespace davidhirtz\yii2\media\console\controllers;

use davidhirtz\yii2\media\models\AssetInterface;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\ModuleTrait;
use Yii;
use yii\console\Controller;

/**
 * Handles media module files
 */
class FileController extends Controller
{
    use ModuleTrait;

    /**
     * Removes unused files
     */
    public function actionClear()
    {
        $fileCountAttributes = [];

        foreach (static::getModule()->assets as $asset) {
            /** @var AssetInterface $asset */
            $asset = Yii::createObject(is_array($asset) ? $asset['class'] : $asset);
            $fileCountAttributes[] = $asset->getFileCountAttribute();
        }

        $query = File::find();
        $deletedCount = 0;

        foreach ($fileCountAttributes as $fileCountAttribute) {
            $query->andWhere([$fileCountAttribute => 0]);
        }

        /** @var File $file */
        foreach ($query->each() as $file) {
            if ($file->delete()) {
                $deletedCount++;
            }
        }

        $this->stdout("{$deletedCount} unused files were deleted" . PHP_EOL);
    }
}