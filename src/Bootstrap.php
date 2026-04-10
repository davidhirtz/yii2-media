<?php

declare(strict_types=1);

namespace Hirtz\Media;

use Hirtz\Media\console\controllers\FileController;
use Hirtz\Media\console\controllers\TransformationController;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Modules\Admin\Controllers\DashboardController;
use Hirtz\Skeleton\Web\Application;
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

        $app->getI18n()->translations['media'] ??= [
            'class' => PhpMessageSource::class,
            'basePath' => '@media/../messages',
        ];

        $app->extendModules([
            'admin' => [
                'modules' => [
                    'media' => [
                        'class' => Modules\Admin\Module::class,
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
        $app->addUrlManagerRules(["$uploadPath/<path:.*>" => 'media/transformation/create'], true);

        DashboardController::addRoles([
            File::AUTH_FILE_UPDATE,
            Folder::AUTH_FOLDER_UPDATE,
        ]);

        $app->setMigrationNamespace('Hirtz\Media\Migrations');
    }
}
