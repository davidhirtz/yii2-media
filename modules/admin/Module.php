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
    ];

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (!Yii::$app->getRequest()->getIsConsoleRequest()) {

            if (Yii::$app->getUser()->can('upload')) {
                if (!$this->navbarItems) {
                    $this->navbarItems = [
                        [
                            'label' => $this->name ?: Yii::t('media', 'Assets'),
                            'icon' => 'images',
                            'url' => ['/admin/file/index'],
                            'active' => ['admin/file', 'admin/folder'],
                        ]
                    ];
                }
            }

            $this->module->navbarItems = array_merge($this->module->navbarItems, $this->navbarItems);
        }

        $this->module->controllerMap = array_merge($this->module->controllerMap, $this->defaultControllerMap, $this->controllerMap);

        parent::init();
    }
}