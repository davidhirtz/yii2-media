<?php

namespace davidhirtz\yii2\media\console\controllers;

use davidhirtz\yii2\media\models\interfaces\AssetInterface;
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
     * @noinspection PhpUnused
     */
    public function actionClear(): void
    {
        $fileCountAttributes = [];

        foreach (static::getModule()->assets as $asset) {
            $fileCountAttributes[] = $asset::instance()->getFileCountAttribute();
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

        $this->stdout("$deletedCount unused files were deleted" . PHP_EOL);
    }
}