<?php

namespace davidhirtz\yii2\media\modules\admin;

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
     * @var array containing the admin menu items
     */
    public $navbarItems = [];

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
            if (!$this->navbarItems) {
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

            $this->module->navbarItems = array_merge($this->module->navbarItems, $this->navbarItems);
            $this->module->panels = array_merge($this->module->panels, $this->panels);
        }

        $this->module->controllerMap = array_merge($this->module->controllerMap, $this->defaultControllerMap, $this->controllerMap);

        parent::init();
    }
}