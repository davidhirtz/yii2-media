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
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
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
            ->filterWhere(['folder_id' => $folder->id, 'basename' => pathinfo($filename, PATHINFO_FILENAME), 'extension' => $extension !== 'webp' ? pathinfo($filename, PATHINFO_EXTENSION) : null])
            ->limit(1)
            ->one();

        if (!$file) {
            throw new NotFoundHttpException;
        }

        $transformation = new Transformation;
        $transformation->name = $transformationName;
        $transformation->extension = $extension;

        $file->populateRelation('folder', $folder);
        $transformation->populateRelation('file', $file);

        return Yii::$app->getResponse()->sendFile($transformation->save() ? $transformation->getFilePath() : ($folder->getUploadPath() . $file->getFilename()), null, [
            'inline' => true,
        ]);
    }
}