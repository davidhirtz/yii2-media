<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Controllers;

use Hirtz\Media\Models\Asset;
use Override;

/**
 * Serves every registered subclass that did not point itself at a controller of its own.
 */
class AssetController extends AbstractAssetController
{
    #[Override]
    public function init(): void
    {
        $this->assetClasses = array_values(array_filter(
            static::getModule()->getAssetClasses(),
            fn (string $assetClass): bool => $assetClass::getAdminControllerRoute() === Asset::getAdminControllerRoute()
        ));

        parent::init();
    }
}
