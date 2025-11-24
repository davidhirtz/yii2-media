<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\navs;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\admin\Module;
use davidhirtz\yii2\media\modules\admin\widgets\traits\FileWidgetTrait;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\widgets\navs\NavItem;
use davidhirtz\yii2\skeleton\widgets\navs\Submenu;
use Override;
use Yii;

/**
 * @property File|null $model
 */
class MediaSubmenu extends Submenu
{
    use ModuleTrait;
    use FileWidgetTrait;

    protected readonly Module $module;

    public function __construct()
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('admin')->getModule('media');
        $this->module = $module;

        parent::__construct();
    }

    #[Override]
    public function configure(): void
    {
        $this->title ??= $this->module->getName();
        $this->url ??= $this->module->url;
        $this->items = $this->getDefaultItems();

        $this->setBreadcrumbs();

        parent::configure();
    }

    protected function getDefaultItems(): array
    {
        return [
            $this->getFileIndex(),
            $this->getFolderIndex(),
        ];
    }

    protected function getFileIndex(): ?NavItem
    {
        return Yii::$app->getUser()->can(File::AUTH_FILE_UPDATE)
            ? NavItem::make()
                ->icon('images')
                ->label(Yii::t('media', 'Files'))
                ->url(['file/index'])
                ->routes(['file/'])
            : null;
    }

    protected function getFolderIndex(): ?NavItem
    {
        return Yii::$app->getUser()->can(Folder::AUTH_FOLDER_UPDATE)
            ? NavItem::make()
                ->icon('folder-open')
                ->label(Yii::t('media', 'Folders'))
                ->url(['folder/index'])
                ->routes(['folder/'])
            : null;
    }

    protected function setBreadcrumbs(): void
    {
        $this->view->addBreadcrumb($this->module->getName(), ['/admin/file/index']);

        if ($this->file) {
            $this->view->addBreadcrumb($this->file->folder->name, ['/admin/file/index', 'folder' => $this->file->folder_id]);
        }
    }
}
