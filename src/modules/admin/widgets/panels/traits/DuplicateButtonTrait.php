<?php

declare(strict_types=1);

namespace Hirtz\Media\modules\admin\widgets\panels\traits;

use Hirtz\Media\modules\admin\controllers\FileController;
use Hirtz\Skeleton\html\Button;
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
