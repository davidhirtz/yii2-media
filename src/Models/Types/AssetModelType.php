<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Types;

use Hirtz\Media\Models\Interfaces\AssetModelTypeInterface;
use Hirtz\Media\Models\Types\Traits\AssetModelTypeTrait;
use Hirtz\Skeleton\Models\Types\Type;

/**
 * For a model with nothing else to declare; the cms entry and section types implement the interface themselves.
 */
class AssetModelType extends Type implements AssetModelTypeInterface
{
    use AssetModelTypeTrait;
}
