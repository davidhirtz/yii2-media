<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\Asset;
use Hirtz\Skeleton\Widgets\Navs\ModelHeader;

/**
 * The chain {@see Asset::getAdminParent()} answers leads back to the owning record without this widget knowing its
 * bundle.
 *
 * @extends ModelHeader<Asset>
 */
class AssetHeader extends ModelHeader
{
}
