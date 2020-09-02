<?php

namespace davidhirtz\yii2\media\console\controllers;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\models\Transformation;
use davidhirtz\yii2\media\Module;
use davidhirtz\yii2\skeleton\helpers\FileHelper;
use Yii;
use yii\helpers\Console;

/**
 * Module "media" transformations.
 * @package davidhirtz\yii2\media\console\controllers
 *
 * @property Module $module
 */
class TransformationController extends \yii\console\Controller
{
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

        /** @var Module $module */
        $module = Yii::$app->getModule('media');

        foreach ($module->transformations as $name => $transformation) {
            if (!isset($transformations[$name])) {
                $transformations[$name] = 0;
            }
        }

        ksort($transformations);

        $this->stdout("Transformations:" . PHP_EOL);

        foreach ($transformations as $name => $count) {
            $this->stdout("  - ");
            $this->stdout("{$name}  ({$count})" . PHP_EOL, !isset($module->transformations[$name]) ? Console::FG_RED : ($count > 0 ? Console::FG_GREEN : null));
        }

        // @todo unattended folders
    }

    /**
     * Deletes a transformation.
     * @param string $name
     * @return bool|int
     */
    public function actionDelete($name)
    {
        if (!Transformation::find()->where(['name' => $name])->exists()) {
            return $this->stdout("Transformation \"{$name}\" does not exist." . PHP_EOL, Console::FG_RED);
        }

        $folders = Folder::find()->all();

        foreach ($folders as $folder) {
            FileHelper::removeDirectory($folder->getUploadPath() . $name);
        }

        Transformation::deleteAll(['name' => $name]);

        return $this->stdout("Transformation \"{$name}\" deleted." . PHP_EOL, Console::FG_RED);
    }
}