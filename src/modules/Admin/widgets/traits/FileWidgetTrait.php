<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Traits;

use Hirtz\Media\Models\File;

trait FileWidgetTrait
{
    protected ?File $file = null;

    public function file(?File $file): static
    {
        $this->file = $file;
        return $this;
    }
}
