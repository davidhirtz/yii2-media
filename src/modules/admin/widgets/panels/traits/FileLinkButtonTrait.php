<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\panels\traits;

use davidhirtz\yii2\skeleton\html\Button;
use Stringable;
use Yii;

trait FileLinkButtonTrait
{
    protected function getFileLinkButton(): Stringable
    {
        return Button::make()
            ->secondary()
            ->text(Yii::t('media', 'Show file'))
            ->icon('link')
            ->href($this->model->getUrl())
            ->target('blank');
    }
}
