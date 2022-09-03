<?php

namespace davidhirtz\yii2\media\console\controllers;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\models\Transformation;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\helpers\FileHelper;
use yii\console\Controller;
use yii\helpers\Console;

/**
 * Handles media module transformations
 */
class TransformationController extends Controller
{
    use ModuleTrait;

    /**
     * Lists all active and inactive transformations.
     */
    public function actionIndex()
    {
        $transformations = Transformation::find()
            ->select('COUNT(*)')
            ->groupBy('name')
            ->orderBy('name')
            ->indexBy('name')
            ->column();

        foreach (static::getModule()->transformations as $name => $transformation) {
            if (!isset($transformations[$name])) {
                $transformations[$name] = 0;
            }
        }

        $this->stdout('Transformations:' . PHP_EOL);
        ksort($transformations);

        foreach ($transformations as $name => $count) {
            $this->stdout("  - ");
            $this->stdout("{$name}  ({$count})" . PHP_EOL, !isset(static::getModule()->transformations[$name]) ? Console::FG_RED : ($count > 0 ? Console::FG_GREEN : null));
        }
    }

    /**
     * Deletes a transformation.
     *
     * @param string $name
     * @return bool|int
     */
    public function actionDelete($name)
    {
        // Make sure transformation name doesn't try to temper with the file system (eg. "../")
        if (!($name = basename(str_replace('.', '', $name)))) {
            return PHP_EOL;
        }

        $query = Transformation::find()
            ->where(['name' => $name]);

        /** @var Transformation $transformation */
        foreach ($query->each() as $transformation) {
            if ($transformation->delete()) {
                if ($this->interactive) {
                    $this->stdout(' > Deleted file ' . $transformation->getFilePath() . PHP_EOL);
                }
            }
        }

        $folders = Folder::find()->all();

        foreach ($folders as $folder) {
            FileHelper::removeDirectory($path = $folder->getUploadPath() . $name);

            if ($this->interactive) {
                $this->stdout(" > Removed folder {$path}" . PHP_EOL);
            }
        }

        return $this->stdout("Transformations \"{$name}\" deleted" . PHP_EOL, Console::FG_GREEN);
    }
}