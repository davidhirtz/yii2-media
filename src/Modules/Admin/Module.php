<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin;

use Hirtz\Media\Modules\Admin\Widgets\Navs\MediaNavItem;
use Hirtz\Skeleton\Modules\Admin\ModuleInterface;
use Hirtz\Skeleton\Widgets\Navs\Nav;
use Hirtz\Skeleton\Widgets\Panels\Dashboard;
use Override;

/**
 * @property \Hirtz\Skeleton\Modules\Admin\Module $module
 */
class Module extends \Hirtz\Skeleton\Base\Module implements ModuleInterface
{
    public $defaultRoute = 'file';

    public ?array $cropRatios = null;

    #[Override]
    public function dashboard(Dashboard $dashboard): Dashboard
    {
        return $dashboard;
    }

    #[Override]
    public function aside(Nav $nav): Nav
    {
        return $nav->addItem(MediaNavItem::make());
    }
}
