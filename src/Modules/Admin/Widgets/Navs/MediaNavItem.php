<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Skeleton\I18n\Lang;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Widgets\Navs\NavItem;
use Override;
use Yii;

class MediaNavItem extends NavItem
{
    public function __construct(array $config = [])
    {
        $this->label ??= Lang::t('media', 'MEDIA_NAV_ITEM_MEDIA');
        $this->icon ??= 'photo-film';
        $this->order ??= 20;
        $this->url ??= ['/admin/media/file/index'];
        $this->roles ??= [File::AUTH_FILE_UPDATE, Folder::AUTH_FOLDER_UPDATE];

        parent::__construct($config);
    }

    #[Override]
    protected function configure(): void
    {
        $this->addSubnavItems();
        parent::configure();
    }

    protected function addSubnavItems(): void
    {
        $this->addItem($this->getFilesItem(), $this->getFoldersItem());
    }

    protected function getFilesItem(): NavItem
    {
        return NavItem::make()
            ->icon('file-image')
            ->label(Lang::t('media', 'COMMON_FILES'))
            ->order(10)
            ->url(['/admin/media/file/index'])
            ->roles([File::AUTH_FILE_UPDATE])
            ->routes(['media/file']);
    }

    protected function getFoldersItem(): NavItem
    {
        return NavItem::make()
            ->icon('folder-open')
            ->label(Lang::t('media', 'COMMON_FOLDERS'))
            ->order(20)
            ->url(['/admin/media/folder/index'])
            ->roles([Folder::AUTH_FOLDER_UPDATE])
            ->routes(['media/folder']);
    }
}
