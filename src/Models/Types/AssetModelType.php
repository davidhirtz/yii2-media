<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Types;

use Hirtz\Media\Models\Interfaces\AssetModelTypeInterface;
use Hirtz\Media\Models\Types\Traits\AssetModelTypeTrait;
use Hirtz\Skeleton\Models\Types\Type;

/**
 * The type of a model that has assets, for a model with nothing else to declare — a bundle's own type class says
 * so itself, as the cms entry and section types do.
 */
class AssetModelType extends Type implements AssetModelTypeInterface
{
    use AssetModelTypeTrait;
}
