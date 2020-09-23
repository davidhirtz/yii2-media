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
        $folderName = array_shift($path);
        $transformationName = array_shift($path);
        $filename = implode('/', $path);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!isset($this->module->transformations[$transformationName])) {
            throw new NotFoundHttpException();
        }

        /** @var Folder $folder */
        $folder = Folder::find()
            ->where(['path' => $folderName])
            ->limit(1)
            ->one();

        if (!$folder) {
            throw new NotFoundHttpException();
        }

        /** @var File $file */
        $file = File::find()
            ->filterWhere([
                'folder_id' => $folder->id,
                'basename' => substr($filename, 0, -strlen($extension) - 1),
                'extension' => !is_array($this->module->transformationExtensions) || !in_array($extension, $this->module->transformationExtensions) ? pathinfo($filename, PATHINFO_EXTENSION) : null,
            ])
            ->limit(1)
            ->one();

        if (!$file) {
            throw new NotFoundHttpException();
        }

        // Make sure transformation was not previously created. This can happen when CDN caches like Cloudfront request
        // the transformation controller even though the file was already created. In this case the file is simply
        // sent and will not be created again.
        $transformation = Transformation::findOne([
            'file_id' => $file->id,
            'name' => $transformationName,
            'extension' => $extension,
        ]);

        if (!$transformation) {
            $transformation = new Transformation();
            $transformation->name = $transformationName;
            $transformation->extension = $extension;
        }

        $file->populateFolderRelation($folder);
        $transformation->populateRelation('file', $file);

        if ($transformation->getIsNewRecord()) {
            $transformation->save();
        }

        return Yii::$app->getResponse()->sendFile(!$transformation->getIsNewRecord() ? $transformation->getFilePath() : ($folder->getUploadPath() . $file->getFilename()), null, [
            'inline' => true,
        ]);
    }
}