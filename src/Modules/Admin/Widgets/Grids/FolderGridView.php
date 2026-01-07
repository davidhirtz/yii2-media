<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids;

use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Helpers\Html;
use Hirtz\Skeleton\Html\A;
use Hirtz\Skeleton\Html\Button;
use Hirtz\Skeleton\Widgets\Grids\Columns\BadgeColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\ButtonColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\Buttons\DraggableSortGridButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Buttons\ViewGridButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Column;
use Hirtz\Skeleton\Widgets\Grids\Columns\DataColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\RelativeTimeColumn;
use Hirtz\Skeleton\Widgets\Grids\GridView;
use Override;
use Stringable;
use Yii;
use yii\data\ActiveDataProvider;

/**
 * @extends GridView<Folder>
 * @property ActiveDataProvider $dataProvider
 */
class FolderGridView extends GridView
{
    use ModuleTrait;

    #[Override]
    protected function configure(): void
    {
        $this->model ??= Folder::instance();

        $this->header ??= [
            $this->search->getToolbarItem(),
        ];

        $this->columns ??= [
            $this->getNameColumn(),
            $this->getFileCountColumn(),
            $this->getUpdatedAtColumn(),
            $this->getButtonColumn(),
        ];

        $this->footer ??= [
            $this->getCreateFolderButton(),
        ];

        parent::configure();
    }

    protected function getCreateFolderButton(): ?Stringable
    {
        return Yii::$app->getUser()->can(Folder::AUTH_FOLDER_CREATE)
            ? Button::make()
                ->primary()
                ->text(Yii::t('media', 'New Folder'))
                ->icon('plus')
                ->href(['/admin/folder/create'])
            : null;
    }

    protected function getNameColumn(): Column
    {
        return DataColumn::make()
            ->property('name')
            ->content(fn (Folder $folder) => A::make()
                ->content(Html::markKeywords(Html::encode($folder->name), $this->search->getKeywords()))
                ->href($this->getRoute($folder))
                ->class('strong'));
    }

    protected function getFileCountColumn(): Column
    {
        return BadgeColumn::make()
            ->property('file_count')
            ->url(fn (Folder $folder) => ['file/index', 'folder' => $folder->id]);
    }

    protected function getUpdatedAtColumn(): Column
    {
        return RelativeTimeColumn::make()
            ->property('updated_at');
    }

    protected function getButtonColumn(): Column
    {
        return ButtonColumn::make()
            ->content($this->getButtonColumnContent(...));
    }

    protected function getButtonColumnContent(Folder $folder): array
    {
        $buttons = [];

        if ($this->isSortable()) {
            $buttons[] = DraggableSortGridButton::make();
        }

        $buttons[] = ViewGridButton::make()
            ->model($folder);

        return $buttons;
    }
}
