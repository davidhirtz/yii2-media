<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\nav\base;

use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\media\modules\admin\models\forms\FileForm;
use Yii;

/**
 * Class FileSubmenu.
 * @package davidhirtz\yii2\media\modules\admin\widgets\nav\base
 */
class FileSubmenu extends \davidhirtz\yii2\skeleton\widgets\fontawesome\Submenu
{
    use ModuleTrait;

    /**
     * @var FileForm
     */
    public $file;

    /**
     * Initializes the nav items.
     */
    public function init()
    {
        if (!$this->items) {
            if ($this->file) {
                $this->items = [
                    [
                        'label' => Yii::t('media', 'File'),
                        'url' => ['file/update', 'id' => $this->file->id],
                        'active' => ['file/'],
                        'icon' => 'book hidden-xs',
                    ],
                    [
                        'label' => Yii::t('media', 'Sections'),
                        'url' => ['section/index', 'file' => $this->file->id],
                        'visible' => static::getModule()->enableSections,
                        'badge' => $this->file->section_count ?: null,
                        'badgeOptions' => [
                            'id' => 'file-section-count',
                            'class' => 'badge',
                        ],
                        'icon' => 'th-list hidden-xs',
                        'active' => ['section'],
                        'options' => [
                            'class' => 'file-sections',
                        ],
                    ],
                ];
            }
        }

        if (!$this->title) {
            $this->title = $this->file ? $this->file->getI18nAttribute('name') : Yii::t('media', 'Files');
        }

        parent::init();
    }
}