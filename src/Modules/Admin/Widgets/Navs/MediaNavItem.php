<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Widgets\Navs\NavItem;
use Override;
use Yii;

class MediaNavItem extends NavItem
{
    public function __construct(array $config = [])
    {
        $this->label ??= Yii::t('media', 'COMMON_FILES');
        $this->icon ??= 'photo-film';
        $this->order ??= 20;
        $this->url ??= ['/admin/media/file/index'];
        $this->roles ??= [File::AUTH_FILE_UPDATE, Folder::AUTH_FOLDER_UPDATE];

        $this->routes(['media/file']);

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
        $this->addItem($this->getFoldersItem());
    }


    protected function getFoldersItem(): NavItem
    {
        return NavItem::make()
            //->icon('folder-open')
            ->label(Yii::t('media', 'COMMON_FOLDERS'))
            ->order(20)
            ->roles([Folder::AUTH_FOLDER_UPDATE])
            ->routes(['media/folder'])
            ->url(['/admin/media/folder/index']);
    }
}
