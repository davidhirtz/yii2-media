<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\panels\traits;

use davidhirtz\yii2\media\modules\admin\controllers\FileController;
use davidhirtz\yii2\skeleton\html\Button;
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
