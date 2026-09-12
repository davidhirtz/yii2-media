<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Skeleton\Widgets\Navs\NavItem;

/**
 * The asset count of a record, in whichever submenu shows it. A file upload refreshes it out of band, which only
 * works while every submenu renders it under the same id.
 */
class AssetSubmenuItem extends NavItem
{
    final public const string ID = 'assets';

    public function __construct(array $config = [])
    {
        $this->attributes['id'] ??= self::ID;
        $this->icon ??= 'photo-film';

        parent::__construct($config);
    }
}
