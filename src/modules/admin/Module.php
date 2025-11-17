<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\controllers\FileController;
use davidhirtz\yii2\media\modules\admin\controllers\FolderController;
use davidhirtz\yii2\media\modules\admin\controllers\TransformationController;
use davidhirtz\yii2\skeleton\helpers\ArrayHelper;
use davidhirtz\yii2\skeleton\modules\admin\config\MainMenuItemConfig;
use davidhirtz\yii2\skeleton\modules\admin\ModuleInterface;
use Override;
use Yii;

/**
 * @property \davidhirtz\yii2\skeleton\modules\admin\Module $module
 */
class Module extends \davidhirtz\yii2\skeleton\base\Module implements ModuleInterface
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
