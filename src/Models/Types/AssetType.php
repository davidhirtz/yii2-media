<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Types;

use Hirtz\Media\Models\Interfaces\TransformationTypeInterface;
use Hirtz\Media\Models\Types\Traits\TransformationTypeTrait;
use Hirtz\Skeleton\Models\Types\Type;

/**
 * Sizes and transformations are declared here too, since a hotspot asset renders through the hotspots' presets.
 */
class AssetType extends Type implements TransformationTypeInterface
{
    use TransformationTypeTrait;
}
