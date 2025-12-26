<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Panels\Traits;

use Hirtz\Skeleton\Html\Button;
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
