<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\nav\base;

use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\media\modules\admin\models\forms\FileForm;
use Yii;

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
                        'icon' => 'images hidden-xs',
                    ],
                    [
                        'label' => Yii::t('media', 'Folders'),
                        'url' => ['folder/index'],
                        'active' => ['folder/'],
                        'icon' => 'folder-open hidden-xs',
                    ],
                ];
        }

        if (!$this->title) {
            $this->title = Yii::t('media', 'Assets');
        }

        parent::init();
    }
}