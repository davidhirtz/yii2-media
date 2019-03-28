<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\nav\base;

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
            $this->title = Html::a(Yii::t('media', 'Assets'), ['/admin/file/index']);
        }

        parent::init();
    }
}