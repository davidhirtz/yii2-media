<?php

declare(strict_types=1);

namespace Hirtz\Media\modules\admin\widgets\traits;

use Hirtz\Media\models\File;

trait FileWidgetTrait
{
    protected ?File $file = null;

    public function file(?File $file): static
    {
        $this->file = $file;
        return $this;
    }
}
