<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Types;

use Hirtz\Media\Models\Interfaces\AssetModelTypeInterface;
use Hirtz\Media\Models\Types\Traits\AssetModelTypeTrait;
use Hirtz\Skeleton\Models\Types\Type;

/**
 * An asset carries the sizes and transformations of the model it belongs to, so the two are declared on its type as
 * well — a hotspot asset renders through the hotspots' presets, not its model's.
 */
class AssetType extends Type implements AssetModelTypeInterface
{
    use AssetModelTypeTrait;
}
