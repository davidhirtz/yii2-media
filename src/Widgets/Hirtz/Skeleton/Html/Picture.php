<?php

declare(strict_types=1);

namespace Hirtz\Media\Widgets\Hirtz\Skeleton\Html;

use Hirtz\Skeleton\Html\Base\Tag;
use Hirtz\Skeleton\Html\Traits\TagContentTrait;

class Picture extends Tag
{
    use TagContentTrait;

    protected function getTagName(): string
    {
        return 'picture';
    }
}
