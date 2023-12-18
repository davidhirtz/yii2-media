<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\panels\traits;

use davidhirtz\yii2\skeleton\helpers\Html;
use Yii;

trait DuplicateButtonTrait
{
    protected function getDuplicateButton(array $options = []): string
    {
        return Html::a(Html::iconText('paste', Yii::t('cms', 'Duplicate')), ['duplicate', 'id' => $this->model->id], [
            'class' => 'btn btn-primary',
            'data-method' => 'post',
            ...$options,
        ]);
    }
}