<?php

namespace davidhirtz\yii2\media\composer;

use davidhirtz\yii2\skeleton\composer\BootstrapTrait;
use yii\base\Application;
use yii\base\BootstrapInterface;
use Yii;

/**
 * Class Bootstrap
 * @package davidhirtz\yii2\media\bootstrap
 */
class Bootstrap implements BootstrapInterface
{
    use BootstrapTrait;

    /**
     * @param Application $app
     */
    public function bootstrap($app)
    {
        Yii::setAlias('@media', dirname(__DIR__));

        $this->extendComponent($app, 'i18n', [
            'translations' => [
                'media' => [
                    'class' => 'yii\i18n\PhpMessageSource',
                    'basePath' => '@media/messages',
                ],
            ],
        ]);

        $this->extendModules($app, [
            'admin' => [
                'modules' => [
                    'media' => [
                        'class' => 'davidhirtz\yii2\media\modules\admin\Module',
                    ],
                ],
            ],
            'media' => [
                'class' => 'davidhirtz\yii2\media\Module',
                'uploadPath' => '@webroot/uploads'
            ],
        ]);

        if ($app instanceof \davidhirtz\yii2\skeleton\console\Application) {
            $app->controllerMap['transformation'] = 'davidhirtz\yii2\media\console\controllers\TransformationController';
        }

        $app->getUrlManager()->addRules([trim($app->getModules()['media']['uploadPath'], '/') . '/<path:.*>' => 'media/transformation/create'], false);
        $this->setMigrationNamespace($app, 'davidhirtz\yii2\media\migrations');
    }
}