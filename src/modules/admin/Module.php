<?php

declare(strict_types=1);

namespace Hirtz\Media\modules\admin;

use Hirtz\Media\models\File;
use Hirtz\Media\modules\admin\controllers\FileController;
use Hirtz\Media\modules\admin\controllers\FolderController;
use Hirtz\Media\modules\admin\controllers\TransformationController;
use Hirtz\Skeleton\helpers\ArrayHelper;
use Hirtz\Skeleton\modules\admin\config\MainMenuItemConfig;
use Hirtz\Skeleton\modules\admin\ModuleInterface;
use Override;
use Yii;

/**
 * @property \Hirtz\Skeleton\modules\admin\Module $module
 */
class Module extends \Hirtz\Skeleton\base\Module implements ModuleInterface
{
    public array|string $url = ['/admin/file/index'];
    public ?array $cropRatios = null;

    #[Override]
    public function init(): void
    {
        $this->controllerMap = ArrayHelper::merge($this->getCoreControllerMap(), $this->controllerMap);
        parent::init();
    }

    protected function getCoreControllerMap(): array
    {
        return [
            'file' => [
                'class' => FileController::class,
                'viewPath' => '@media/modules/admin/views/file',
            ],
            'folder' => [
                'class' => FolderController::class,
                'viewPath' => '@media/modules/admin/views/folder',
            ],
            'transformation' => [
                'class' => TransformationController::class,
            ],
        ];
    }

    public function getDashboardPanels(): array
    {
        return [];
    }

    public function getName(): string
    {
        return Yii::t('media', 'Files');
    }

    public function getMainMenuItems(): array
    {
        return [
            'media' => new MainMenuItemConfig(
                label: $this->getName(),
                url: $this->url,
                icon: 'images',
                roles: [
                    File::AUTH_FILE_UPDATE,
                    'folderUpdate',
                ],
                routes: [
                    'admin/file',
                    'admin/folder',
                ],
            ),
        ];
    }
}
