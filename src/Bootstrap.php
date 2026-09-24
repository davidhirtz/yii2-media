<?php

declare(strict_types=1);

namespace Hirtz\Media;

use Hirtz\Media\Console\Controllers\FileController;
use Hirtz\Media\Console\Controllers\TransformationController;
use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Helpers\EventHelper;
use Hirtz\Skeleton\Html\Div;
use Hirtz\Skeleton\Html\Span;
use Hirtz\Skeleton\Modules\Admin\Controllers\DashboardController;
use Hirtz\Skeleton\Modules\Admin\Widgets\Panels\ServerInfo;
use Hirtz\Skeleton\Models\User;
use Hirtz\Skeleton\Web\Application;
use Hirtz\Skeleton\Widgets\Widget;
use Yii;
use yii\base\BootstrapInterface;
use yii\i18n\PhpMessageSource;

class Bootstrap implements BootstrapInterface
{
    /**
     * @param Application<User> $app
     */
    public function bootstrap($app): void
    {
        Yii::setAlias('@media', __DIR__);
        FolderCollection::reset();

        $app->getI18n()->translations['media'] ??= [
            'class' => PhpMessageSource::class,
            'basePath' => '@media/../messages',
            'forceTranslation' => true,
        ];

        $app->extendComponent('search', [
            'models' => [
                File::class,
                Folder::class,
            ],
        ]);

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
                'uploadPath' => 'uploads',
            ],
        ]);

        if ($app->getRequest()->getIsConsoleRequest()) {
            $app->controllerMap['file'] = FileController::class;
            $app->controllerMap['transformation'] = TransformationController::class;
        }

        /** @see TransformationController::actionCreate */
        $uploadPath = trim((string)$app->getModules()['media']['uploadPath'], '/');

        $app->addUrlManagerRules(["$uploadPath/<path:.*>" => 'media/transformation/create'], true);

        DashboardController::addRoles(static fn (): array => [
            File::AUTH_FILE,
            Folder::AUTH_FOLDER,
        ]);

        $app->setMigrationNamespace('Hirtz\Media\Migrations');

        EventHelper::on(
            ServerInfo::class,
            Widget::EVENT_CONFIGURE,
            static fn (ServerInfo $info) => $info->rows(self::addImageFormatsRow(...)),
        );
    }

    /**
     * A server whose image library lists a format it cannot write serves the next one instead (#271), which nobody
     * would notice without this row.
     */
    private static function addImageFormatsRow(ServerInfo $info): void
    {
        $module = File::getModule();
        $extensions = $module->transformationExtensions;

        if (!$extensions) {
            return;
        }

        $encodable = $module->getTransformationExtensions();
        $missing = array_values(array_diff($extensions, $encodable));
        $value = Div::make()
            ->class('badge-list');

        foreach ($extensions as $extension) {
            $value->addContent(Span::make()
                ->class('badge')
                ->addClass(in_array($extension, $missing, true) ? 'badge-warning' : 'badge-success')
                ->text(strtoupper($extension)));
        }

        if ($missing) {
            $value .= Div::make()
                ->class('form-hint')
                ->text(Yii::t('media', 'TRANSFORMATION_EXTENSIONS_UNSUPPORTED', [
                    'extensions' => strtoupper(implode(', ', $missing)),
                ]));
        }

        $info->addRow(Yii::t('media', 'TRANSFORMATION_EXTENSIONS_LABEL'), $value);
    }
}
