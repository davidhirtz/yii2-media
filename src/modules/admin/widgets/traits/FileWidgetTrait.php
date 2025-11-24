<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\traits;

use davidhirtz\yii2\media\models\File;

trait FileWidgetTrait
{
    protected ?File $file = null;

    public function file(?File $file): static
    {
        $this->file = $file;
        return $this;
    }
}