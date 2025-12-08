<?php

declare(strict_types=1);

namespace Hirtz\Media\modules\admin\widgets\grids;

use Hirtz\Media\models\Folder;
use Hirtz\Media\modules\ModuleTrait;
use Hirtz\Skeleton\helpers\Html;
use Hirtz\Skeleton\html\A;
use Hirtz\Skeleton\html\Button;
use Hirtz\Skeleton\widgets\grids\columns\BadgeColumn;
use Hirtz\Skeleton\widgets\grids\columns\ButtonColumn;
use Hirtz\Skeleton\widgets\grids\columns\buttons\DraggableSortGridButton;
use Hirtz\Skeleton\widgets\grids\columns\buttons\ViewGridButton;
use Hirtz\Skeleton\widgets\grids\columns\Column;
use Hirtz\Skeleton\widgets\grids\columns\DataColumn;
use Hirtz\Skeleton\widgets\grids\columns\RelativeTimeColumn;
use Hirtz\Skeleton\widgets\grids\GridView;
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
            $this->getButtonsColumn(),
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

    protected function getButtonsColumn(): Column
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
