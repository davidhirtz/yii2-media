<?php

namespace davidhirtz\yii2\media\console\controllers;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\models\Transformation;
use davidhirtz\yii2\media\Module;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\helpers\FileHelper;
use yii\helpers\Console;

/**
 * Module "media" transformations.
 * @package davidhirtz\yii2\media\console\controllers
 */
class TransformationController extends \yii\console\Controller
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

        $this->stdout("Transformations:" . PHP_EOL);
        ksort($transformations);

        foreach ($transformations as $name => $count) {
            $this->stdout("  - ");
            $this->stdout("{$name}  ({$count})" . PHP_EOL, !isset(static::getModule()->transformations[$name]) ? Console::FG_RED : ($count > 0 ? Console::FG_GREEN : null));
        }
    }

    /**
     * Deletes a transformation.
     * @param string $name
     * @return bool|int
     */
    public function actionDelete($name)
    {
        if (!($name = basename(str_replace('.', '', $name)))) {
            return PHP_EOL;
        }

        $query = Transformation::find()
            ->where(['name' => $name]);

        /** @var Transformation $transformation */
        foreach ($query->each() as $transformation) {
            $transformation->delete();
        }

        $folders = Folder::find()->all();

        foreach ($folders as $folder) {
            FileHelper::removeDirectory($folder->getUploadPath() . $name);
        }

        return $this->stdout("Transformation \"{$name}\" deleted." . PHP_EOL, Console::FG_RED);
    }
}