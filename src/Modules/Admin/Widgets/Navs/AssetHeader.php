<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\Asset;
use Hirtz\Skeleton\Widgets\Navs\ModelHeader;

/**
 * The header of a page scoped to one asset, whatever the asset hangs on: the chain
 * {@see Asset::getAdminParent()} answers leads back to the owning record without this widget knowing the
 * record's bundle.
 *
 * @extends ModelHeader<Asset>
 */
class AssetHeader extends ModelHeader
{
}
