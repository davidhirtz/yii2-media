<?php

namespace davidhirtz\yii2\media;

use davidhirtz\yii2\media\console\controllers\FileController;
use davidhirtz\yii2\media\console\controllers\TransformationController;
use davidhirtz\yii2\skeleton\web\Application;
use Yii;
use yii\base\BootstrapInterface;
use yii\i18n\PhpMessageSource;

class Bootstrap implements BootstrapInterface
{
    /**
     * @param Application $app
     */
    public function bootstrap($app): void
    {
        Yii::setAlias('@media', __DIR__);

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
                        'class' => modules\admin\Module::class,
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

        /** @see controllers\TransformationController::actionCreate */
        $uploadPath = trim((string)$app->getModules()['media']['uploadPath'], '/');
        $app->addUrlManagerRules(["$uploadPath/<path:.*>" => 'media/transformation/create']);

        $app->setMigrationNamespace('davidhirtz\yii2\media\migrations');
    }
}
