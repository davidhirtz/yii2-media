<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin;

use davidhirtz\yii2\media\assets\CropperJsAsset;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\controllers\FileController;
use davidhirtz\yii2\media\modules\admin\controllers\FolderController;
use davidhirtz\yii2\media\modules\admin\controllers\TransformationController;
use davidhirtz\yii2\skeleton\helpers\ArrayHelper;
use davidhirtz\yii2\skeleton\modules\admin\ModuleInterface;
use Yii;

/**
 * @property \davidhirtz\yii2\skeleton\modules\admin\Module $module
 */
class Module extends \davidhirtz\yii2\skeleton\base\Module implements ModuleInterface
{
    /**
     * @var array the navbar item url
     */
    public array $url = ['/admin/file/index'];

    /**
     * @var array|null containing the crop ratios for {@see CropperJsAsset}.
     */
    public ?array $cropRatios = null;

    public $layout = '@skeleton/modules/admin/views/layouts/main';

    #[\Override]
    public function init(): void
    {
        $this->cropRatios ??= [
            'NaN' => Yii::t('media', 'Free'),
            1 => Yii::t('media', '1:1'),
            strval(4 / 3) => Yii::t('media', '4:3'),
            strval(16 / 9) => Yii::t('media', '16:9'),
        ];

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

    public function getNavBarItems(): array
    {
        return [
            'media' => [
                'label' => $this->getName(),
                'icon' => 'images',
                'url' => $this->url,
                'active' => ['admin/file', 'admin/folder'],
                'roles' => [
                    File::AUTH_FILE_UPDATE,
                    'folderUpdate',
                ],
            ],
        ];
    }
}
