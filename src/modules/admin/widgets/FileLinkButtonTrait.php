<?php

namespace davidhirtz\yii2\media\modules\admin\widgets;

use davidhirtz\yii2\skeleton\helpers\Html;
use Yii;

/**
 * Trait FileLinkButtonTrait
 * @package davidhirtz\yii2\media\modules\admin\widgets
 */
trait FileLinkButtonTrait
{
    /**
     * @return string
     */
    protected function getFileLinkButton()
    {
        return Html::a(Html::iconText('link', Yii::t('media', 'Show file')), $this->model->getUrl(), [
            'class' => 'btn btn-secondary',
            'target' => 'blank',
        ]);
    }
}