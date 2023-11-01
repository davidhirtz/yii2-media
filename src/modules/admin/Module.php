<?php

namespace davidhirtz\yii2\media\modules\admin;

use davidhirtz\yii2\media\assets\CropperJsAsset;
use davidhirtz\yii2\skeleton\modules\admin\widgets\navs\NavBar;
use Yii;

/**
 * Class Module
 * @package davidhirtz\yii2\media\modules\admin
 * @property \davidhirtz\yii2\skeleton\modules\admin\Module $module
 */
class Module extends \yii\base\Module
{
    /**
     * @var string the module display name, defaults to "Media"
     */
    public $name;

    /**
     * @var mixed the navbar item url
     */
    public $url = ['/admin/file/index'];

    /**
     * @var array containing the crop ratios for {@link CropperJsAsset}.
     */
    public $cropRatios;

    /**
     * @var array containing the admin menu items, see {@link NavBar}.
     */
    public $navbarItems;

    /**
     * @var array containing the panel items
     */
    public $panels = [];

    /**
     * @var string
     */
    public $defaultRoute = 'file';

    /**
     * @var string
     */
    public $layout = '@skeleton/modules/admin/views/layouts/main';

    /**
     * @var array
     */
    protected $defaultControllerMap = [
        'file' => [
            'class' => 'davidhirtz\yii2\media\modules\admin\controllers\FileController',
            'viewPath' => '@media/modules/admin/views/file',
        ],
        'folder' => [
            'class' => 'davidhirtz\yii2\media\modules\admin\controllers\FolderController',
            'viewPath' => '@media/modules/admin/views/folder',
        ],
        'transformation' => [
            'class' => 'davidhirtz\yii2\media\modules\admin\controllers\TransformationController',
        ],
    ];

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (!$this->name) {
            $this->name = Yii::t('media', 'Files');
        }

        if (!Yii::$app->getRequest()->getIsConsoleRequest()) {
            if ($this->navbarItems === null) {
                $this->navbarItems = [
                    'media' => [
                        'label' => $this->name,
                        'icon' => 'images',
                        'url' => $this->url,
                        'active' => ['admin/file', 'admin/folder'],
                        'roles' => ['fileUpdate', 'folderUpdate'],
                    ],
                ];
            }

            if ($this->cropRatios === null) {
                $this->cropRatios = [
                    'NaN' => Yii::t('media', 'Free'),
                    1 => Yii::t('media', '1:1'),
                    strval(4 / 3) => Yii::t('media', '4:3'),
                    strval(16 / 9) => Yii::t('media', '16:9'),
                ];
            }

            $this->module->navbarItems = array_merge($this->module->navbarItems, $this->navbarItems);
            $this->module->panels = array_merge($this->module->panels, $this->panels);
        }

        $this->module->controllerMap = array_merge($this->module->controllerMap, $this->defaultControllerMap, $this->controllerMap);

        parent::init();
    }
}