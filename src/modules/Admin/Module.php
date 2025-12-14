<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin;

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Controllers\FileController;
use Hirtz\Media\Modules\Admin\Controllers\FolderController;
use Hirtz\Media\Modules\Admin\Controllers\TransformationController;
use Hirtz\Skeleton\Helpers\ArrayHelper;
use Hirtz\Skeleton\Modules\Admin\Config\MainMenuItemConfig;
use Hirtz\Skeleton\Modules\Admin\ModuleInterface;
use Override;
use Yii;

/**
 * @property \Hirtz\Skeleton\Modules\Admin\Module $module
 */
class Module extends \Hirtz\Skeleton\Base\Module implements ModuleInterface
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
