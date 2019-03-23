<?php

namespace davidhirtz\yii2\media\controllers;

use davidhirtz\yii2\media\models\Transformation;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\Module;
use davidhirtz\yii2\skeleton\web\Controller;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Class TransformationController.
 * @package davidhirtz\yii2\media\controllers
 *
 * @property Module $module
 */
class TransformationController extends Controller
{
    /**
     * @var string
     */
    public $defaultAction = 'create';

    /**
     * @param string $path
     * @return string|\yii\web\Response
     */
    public function actionCreate($path)
    {
        $path = explode('/', $path);
        $filename = array_pop($path);
        $transformationName = array_pop($path);
        $path = implode('/', $path);

        if (!isset($this->module->transformations[$transformationName])) {
            throw new NotFoundHttpException;
        }

        $folder = Folder::find()
            ->where(['path' => $path])
            ->limit(1)
            ->one();

        if (!$folder) {
            throw new NotFoundHttpException;
        }

        $file = File::find()
            ->where(['folder_id' => $folder->id, 'basename' => pathinfo($filename, PATHINFO_FILENAME), 'extension' => pathinfo($filename, PATHINFO_EXTENSION)])
            ->enabled()
            ->limit(1)
            ->one();

        if (!$file) {
            throw new NotFoundHttpException;
        }

        $transformation = new Transformation;
        $transformation->name = $transformationName;

        $file->populateRelation('folder', $folder);
        $transformation->populateRelation('file', $file);

        return Yii::$app->getResponse()->sendFile($folder->getUploadPath() . ($transformation->save() ? ($transformation->name . '/') : '') . $file->getFilename(), null, [
            'inline' => true,
        ]);
    }
}