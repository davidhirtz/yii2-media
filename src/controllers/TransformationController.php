<?php

namespace davidhirtz\yii2\media\controllers;

use DateTime;
use DateTimeZone;
use davidhirtz\yii2\media\models\forms\TransformationForm;
use davidhirtz\yii2\media\Module;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\web\Controller;
use Exception;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * @property Module $module
 */
class TransformationController extends Controller
{
    use ModuleTrait;

    public $defaultAction = 'create';

    /**
     * @var bool whether debug logging should be disabled for transformation requests. Set to `true` to disable log
     * entries for all transformation requests on a local development environment with an external file system such as
     * AWS S3.
     */
    public bool $disableLogging = false;

    public function init(): void
    {
        if ($this->disableLogging) {
            foreach (Yii::$app->get('log')->targets as $target) {
                $target->enabled = false;
            }
        }

        Yii::$app->getRequest()->enableCsrfValidation = false;

        parent::init();
    }

    public function actionCreate(string $path): Response|string
    {
        // Check if the transformation already exists in the file system. This is needed for external file systems such
        // as S3 which might not be cached yet or are still routed via .htaccess to "web/index.php"
        if (is_file($filePath = static::getModule()->uploadPath . $path)) {
            return $this->sendFile($filePath);
        }

        $form = TransformationForm::create();
        $form->path = $path;

        if (!$form->validate()) {
            throw new NotFoundHttpException();
        }

        try {
            if ($form->transformation->insert()) {
                return $this->sendFile($form->transformation->getFilePath());
            }
        } catch (Exception $exception) {
            // Catching ImageMagick errors ...
            Yii::error($exception);
        }

        // If validation failed (e.g., transformation not applicable), the original file will be returned instead.
        return $this->redirect($form->folder->getUploadUrl() . $form->file->getFilename());
    }

    private function sendFile(string $filePath): Response
    {
        $response = Yii::$app->getResponse();
        $response->getHeaders()->set('Expires', (new DateTime(' + 1 year', new DateTimeZone('GMT')))
            ->format('D, d M Y H:i:s \G\M\T'));

        return $response->sendFile($filePath, null, [
            'inline' => true,
        ]);
    }
}
