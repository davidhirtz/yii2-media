<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin;

use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Data\FileActiveDataProvider;
use Hirtz\Media\Modules\Admin\Widgets\Navs\MediaNavItem;
use Hirtz\Skeleton\Modules\Admin\ModuleInterface;
use Hirtz\Skeleton\Modules\Admin\Widgets\Panels\ContentHealthPanel;
use Hirtz\Skeleton\Widgets\Navs\Nav;
use Hirtz\Skeleton\Widgets\Panels\Dashboard;
use Override;
use Yii;

/**
 * @property \Hirtz\Skeleton\Modules\Admin\Module $module
 */
class Module extends \Hirtz\Skeleton\Base\Module implements ModuleInterface
{
    public $defaultRoute = 'file';

    /**
     * @var array<string, string>
     */
    public ?array $cropRatios = null;

    #[Override]
    public function dashboard(Dashboard $dashboard): Dashboard
    {
        $health = $dashboard->getPanel('health');

        if ($health instanceof ContentHealthPanel) {
            $health->addCheck(
                'file-alt-text',
                static fn (): int => (int)File::find()->whereMissingAltText()->count(),
                static fn (int $count): string => Yii::t('media', 'FILE_MISSING_ALT_TEXT_TEXT', ['count' => $count]),
                ['/admin/media/file/index', 'missing' => FileActiveDataProvider::MISSING_ALT_TEXT],
                [File::AUTH_FILE],
                'image',
            );
        }

        return $dashboard;
    }

    #[Override]
    public function aside(Nav $nav): Nav
    {
        return $nav->addItem(MediaNavItem::make());
    }
}
