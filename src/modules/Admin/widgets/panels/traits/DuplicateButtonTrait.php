<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Forms\Traits;

use Hirtz\Media\Modules\Admin\Controllers\FileController;
use Hirtz\Skeleton\Html\Button;
use Stringable;
use Yii;

trait DuplicateButtonTrait
{
    /**
     * @see FileController::actionDuplicate()
     */
    protected function getDuplicateButton(): ?Stringable
    {
        return Button::make()
            ->primary()
            ->text(Yii::t('media', 'Duplicate'))
            ->icon('paste')
            ->post(['duplicate', 'id' => $this->model->id], true);
    }
}
