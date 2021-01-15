<?php

namespace davidhirtz\yii2\media\composer;

use davidhirtz\yii2\skeleton\web\Application;
use yii\base\BootstrapInterface;
use Yii;

/**
 * Class Bootstrap
 * @package davidhirtz\yii2\media\bootstrap
 */
class Bootstrap implements BootstrapInterface
{
    /**
     * @param Application $app
     */
    public function bootstrap($app)
    {
        Yii::setAlias('@media', dirname(__DIR__));

        $app->extendComponent('i18n', [
            'translations' => [
                'media' => [
                    'class' => 'yii\i18n\PhpMessageSource',
                    'basePath' => '@media/messages',
                ],
            ],
        ]);

        $app->extendModules([
            'admin' => [
                'modules' => [
                    'media' => [
                        'class' => 'davidhirtz\yii2\media\modules\admin\Module',
                    ],
                ],
            ],
            'media' => [
                'class' => 'davidhirtz\yii2\media\Module',
                'uploadPath' => 'uploads'
            ],
        ]);

        if ($app->getRequest()->getIsConsoleRequest()) {
            $app->controllerMap['transformation'] = 'davidhirtz\yii2\media\console\controllers\TransformationController';
        }

        $app->getUrlManager()->addRules([
            trim($app->getModules()['media']['uploadPath'], '/') . '/<path:.*>' => 'media/transformation/create',
        ], false);

        $app->setMigrationNamespace('davidhirtz\yii2\media\migrations');
    }
}