<?php

namespace davidhirtz\yii2\media\composer;

use davidhirtz\yii2\media\console\controllers\FileController;
use davidhirtz\yii2\media\console\controllers\TransformationController;
use davidhirtz\yii2\media\Module;
use davidhirtz\yii2\skeleton\web\Application;
use yii\base\BootstrapInterface;
use Yii;
use yii\i18n\PhpMessageSource;

class Bootstrap implements BootstrapInterface
{
    /**
     * @param Application $app
     */
    public function bootstrap($app): void
    {
        Yii::setAlias('@media', dirname(__DIR__));

        $app->extendComponent('i18n', [
            'translations' => [
                'media' => [
                    'class' => PhpMessageSource::class,
                    'basePath' => '@media/messages',
                ],
            ],
        ]);

        $app->extendModules([
            'admin' => [
                'modules' => [
                    'media' => [
                        'class' => \davidhirtz\yii2\media\modules\admin\Module::class,
                    ],
                ],
            ],
            'media' => [
                'class' => Module::class,
                'uploadPath' => 'uploads'
            ],
        ]);

        if ($app->getRequest()->getIsConsoleRequest()) {
            $app->controllerMap['file'] = FileController::class;
            $app->controllerMap['transformation'] = TransformationController::class;
        }

        $app->getUrlManager()->addRules([
            trim((string)$app->getModules()['media']['uploadPath'], '/') . '/<path:.*>' => 'media/transformation/create',
        ], false);

        $app->setMigrationNamespace('davidhirtz\yii2\media\migrations');
    }
}