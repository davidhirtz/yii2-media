<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\nav\base;

use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\widgets\bootstrap\Toolbar;
use Yii;

/**
 * Class FolderToolbar
 * @package davidhirtz\yii2\media\modules\admin\widgets\nav\base
 * @see \davidhirtz\yii2\media\modules\admin\widgets\panels\FolderToolbar
 */
class FolderToolbar extends Toolbar
{

    /**
     * @inheritDoc
     */
    public function init()
    {
        if ($this->actions === null) {
            $this->actions = $this->hasForm() ? [$this->getFormSubmitButton()] : [$this->getCreateFolderButton()];
        }

        parent::init();
    }

    /**
     * @return string
     */
    protected function getCreateFolderButton()
    {
        if (Yii::$app->getUser()->can('upload')) {
            return Html::a(Html::iconText('plus', Yii::t('media', 'New Folder')), ['create'], ['class' => 'btn btn-primary']);
        }

        return '';
    }
}