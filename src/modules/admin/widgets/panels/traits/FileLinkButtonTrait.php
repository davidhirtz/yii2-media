<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\panels\traits;

use davidhirtz\yii2\skeleton\helpers\Html;
use Yii;

trait FileLinkButtonTrait
{
    protected function getFileLinkButton(): string
    {
        return Html::a(Html::iconText('link', Yii::t('media', 'Show file')), $this->model->getUrl(), [
            'class' => 'btn btn-secondary',
            'target' => 'blank',
        ]);
    }
}