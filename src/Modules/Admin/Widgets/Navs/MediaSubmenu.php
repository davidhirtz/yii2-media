<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Skeleton\Widgets\Navs\NavItem;
use Hirtz\Skeleton\Widgets\Navs\Submenu;
use Override;
use Yii;

class MediaSubmenu extends Submenu
{
    #[Override]
    public function configure(): void
    {
        $this->items = $this->getDefaultItems();
        parent::configure();
    }

    protected function getDefaultItems(): array
    {
        return [
            $this->getFileIndexItem(),
            $this->getFolderIndexItem(),
        ];
    }

    protected function getFileIndexItem(): ?NavItem
    {
        return Yii::$app->getUser()->can(File::AUTH_FILE_UPDATE)
            ? NavItem::make()
                ->icon('images')
                ->label(Yii::t('media', 'Files'))
                ->url(['file/index'])
                ->routes(['file/'])
            : null;
    }

    protected function getFolderIndexItem(): ?NavItem
    {
        return Yii::$app->getUser()->can(Folder::AUTH_FOLDER_UPDATE)
            ? NavItem::make()
                ->icon('folder-open')
                ->label(Yii::t('media', 'Folders'))
                ->url(['folder/index'])
                ->routes(['folder/'])
            : null;
    }
}
