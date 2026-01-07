<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids\Interfaces;

use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Widgets\Grids\GridView;

interface FileRelationGridContainerInterface
{
    public static function make(...$args): static;
    public function file(File $file): static;
    public function grid(GridView $file): static;
}
