<?php

declare(strict_types=1);

namespace Hirtz\Media\Traits;

use Hirtz\Media\Models\File;

trait FilePropertyTrait
{
    protected File $file;

    public function file(File $file): static
    {
        $this->file = $file;
        return $this;
    }
}
