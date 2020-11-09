<?php

namespace davidhirtz\yii2\media\controllers;

use davidhirtz\yii2\media\models\Transformation;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\Module;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\web\Controller;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class TransformationController
 * @package davidhirtz\yii2\media\controllers
 *
 * @property Module $module
 */
class TransformationController extends Controller
{
    use ModuleTrait;

    /**
     * @var bool whether debug logging should be disabled for transformation requests. Set to `true`
     * to disable log entries for all transformation requests on a local development environment with
     * an external file system such as AWS S3.
     */
    public $disableLogging = false;

    /**
     * @var string
     */
    public $defaultAction = 'create';

    /**
     * @inheritDoc
     */
    public function init()
    {
        if ($this->disableLogging) {
            foreach (Yii::$app->get('log')->targets as $target) {
                $target->enabled = false;
            }
        }

        Yii::$app->getRequest()->enableCsrfValidation = false;
        parent::init();
    }

    /**
     * @param string $path
     * @return string|Response
     */
    public function actionCreate($path)
    {
        // Check if the transformation already exists in the file system. This is needed for external file systems such
        // as S3 which might cannot be caught by the .htaccess routing to web/index.php
        if (is_file($filePath = static::getModule()->uploadPath . $path)) {
            return Yii::$app->getResponse()->sendFile($filePath, null, [
                'inline' => true,
            ]);
        }

        $path = explode('/', $path);
        $folderName = array_shift($path);
        $transformationName = array_shift($path);
        $filename = implode('/', $path);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!isset($this->module->transformations[$transformationName])) {
            throw new NotFoundHttpException();
        }

        $folder = Folder::find()
            ->where(['path' => $folderName])
            ->limit(1)
            ->one();

        if (!$folder) {
            throw new NotFoundHttpException();
        }

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

        $transformation = new Transformation();
        $transformation->name = $transformationName;
        $transformation->extension = $extension;

        $file->populateFolderRelation($folder);
        $transformation->populateFileRelation($file);
        $transformation->save();

        // If validation failed (eg. transformation not applicable) the original file will be returned instead.
        $filePath = !$transformation->hasErrors() ? $transformation->getFilePath() : ($folder->getUploadPath() . $file->getFilename());

        return Yii::$app->getResponse()->sendFile($filePath, null, [
            'inline' => true,
        ]);
    }
}