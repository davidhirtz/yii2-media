<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids;

use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Html\A;
use Hirtz\Skeleton\Widgets\Grids\Columns\BadgeColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\ButtonColumn;
use Hirtz\Skeleton\Widgets\Buttons\ReorderButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Buttons\ViewGridButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Column;
use Hirtz\Skeleton\Widgets\Grids\Columns\DataColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\RelativeTimeColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\TypeColumn;
use Hirtz\Skeleton\Widgets\Grids\GridView;
use Hirtz\Skeleton\Widgets\Grids\Toolbars\TypeFilterDropdown;
use Override;
use yii\data\ActiveDataProvider;
use Stringable;
use Yii;

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
        $this->header ??= [
            $this->getTypeDropdown(),
            $this->getSearchInput(),
        ];

        $this->columns ??= [
            $this->getTypeColumn(),
            $this->getNameColumn(),
            $this->getFileCountColumn(),
            $this->getUpdatedAtColumn(),
            $this->getButtonColumn(),
        ];

        parent::configure();
    }

    protected function getTypeDropdown(): ?Stringable
    {
        return TypeFilterDropdown::make()
            ->model(Folder::instance());
    }

    protected function getTypeColumn(): ?Column
    {
        return TypeColumn::make()
            ->url(fn (Folder $folder): array => $folder->getAdminRoute())
            ->visible(count(Folder::getTypeDefinitions()) > 1);
    }

    protected function getNameColumn(): Column
    {
        return DataColumn::make()
            ->property('name')
            ->content($this->getNameLink(...));
    }

    /**
     * The id is literal so that the reorder response can swap the link of a folder that became or stopped being the
     * default.
     */
    public function getNameLink(Folder $folder): A
    {
        $name = $this->search->markKeywords($folder->name);

        return A::make()
            ->content(FolderCollection::isMarkedAsDefault($folder)
                ? Yii::t('media', 'FOLDER_NAME_DEFAULT_FORMAT', ['name' => $name])
                : $name)
            ->href($folder->getAdminRoute())
            ->attribute('id', "folder-name-$folder->id")
            ->class('strong');
    }

    protected function getFileCountColumn(): Column
    {
        return BadgeColumn::make()
            ->property('file_count')
            ->url($this->webuser->can(File::AUTH_FILE)
                ? fn (Folder $folder) => ['file/index', 'folder' => $folder->id]
                : null);
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

    /**
     * A drag writes `position`, which another configured order never reads.
     */
    #[Override]
    protected function isReorderable(): bool
    {
        $order = static::getModule()->defaultFolderOrder;

        // A drop posts the shown order as `1..n`, which reverses a descending one.
        return array_key_first($order) === 'position' && $order['position'] === SORT_ASC && parent::isReorderable();
    }

    /**
     * @return list<Stringable>
     */
    protected function getButtonColumnContent(Folder $folder): array
    {
        $buttons = [];

        if ($this->isReorderable()) {
            $buttons[] = ReorderButton::make();
        }

        $buttons[] = ViewGridButton::make()
            ->model($folder);

        return $buttons;
    }
}
