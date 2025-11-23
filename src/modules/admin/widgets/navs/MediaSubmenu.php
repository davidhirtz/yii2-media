<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\navs;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\admin\Module;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\widgets\navs\NavItem;
use davidhirtz\yii2\skeleton\widgets\navs\Submenu;
use davidhirtz\yii2\skeleton\widgets\traits\ModelWidgetTrait;
use Override;
use Yii;
use yii\helpers\Html;

/**
 * @property File|null $model
 */
class MediaSubmenu extends Submenu
{
    use ModuleTrait;
    use ModelWidgetTrait;

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
                ->label(Yii::t('media', 'Files'))
                ->url(['file/index'])
                ->icon('images')
            : null;
    }

    protected function getFolderIndex(): ?NavItem
    {
        return Yii::$app->getUser()->can(Folder::AUTH_FOLDER_UPDATE)
            ? NavItem::make()
                ->label(Yii::t('media', 'Folders'))
                ->url(['folder/index'])
                ->icon('folder-open')
            : null;
    }

    protected function setBreadcrumbs(): void
    {
        $this->view->addBreadcrumb($this->module->getName(), ['/admin/file/index']);

        if ($this->model) {
            $this->view->addBreadcrumb($this->model->folder->name, ['/admin/file/index', 'folder' => $this->model->folder_id]);
        }
    }
}
