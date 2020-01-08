<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\nav\base;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\Module;
use davidhirtz\yii2\media\modules\ModuleTrait;
use Yii;
use yii\helpers\Html;

/**
 * Class Submenu.
 * @package davidhirtz\yii2\media\modules\admin\widgets\nav\base
 */
class Submenu extends \davidhirtz\yii2\skeleton\widgets\fontawesome\Submenu
{
    use ModuleTrait;

    /**
     * @var File
     */
    public $file;

    /**
     * @var string
     */
    private $_parentModule;

    /**
     * Initializes the nav items.
     */
    public function init()
    {
        if (!$this->items) {
            $this->items = [
                [
                    'label' => Yii::t('media', 'Files'),
                    'url' => ['file/index'],
                    'active' => ['file/'],
                    'icon' => 'images',
                    'labelOptions' => [
                        'class' => 'd-none d-md-inline'
                    ],
                ],
                [
                    'label' => Yii::t('media', 'Folders'),
                    'url' => ['folder/index'],
                    'active' => ['folder/'],
                    'icon' => 'folder-open',
                    'labelOptions' => [
                        'class' => 'd-none d-md-inline'
                    ],
                ],
            ];
        }

        if (!$this->title) {
            $this->title = Html::a($this->getParentModule()->name, $this->getParentModule()->url);
        }

        $this->setBreadcrumbs();

        parent::init();
    }

    /**
     * Sets breadcrumbs.
     */
    protected function setBreadcrumbs()
    {
        $view = $this->getView();
        $view->setBreadcrumb($this->getParentModule()->name, ['/admin/file/index']);

        if ($this->file) {
            $view->setBreadcrumb($this->file->folder->name, ['/admin/file/index', 'folder' => $this->file->folder_id]);
        }
    }

    /**
     * @return Module
     */
    protected function getParentModule(): Module
    {
        if ($this->_parentModule === null) {
            $this->_parentModule = Yii::$app->getModule('admin')->getModule('media');
        }

        return $this->_parentModule;
    }
}