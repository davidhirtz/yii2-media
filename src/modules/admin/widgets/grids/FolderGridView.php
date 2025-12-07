<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\grids;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\html\A;
use davidhirtz\yii2\skeleton\html\Button;
use davidhirtz\yii2\skeleton\widgets\grids\columns\BadgeColumn;
use davidhirtz\yii2\skeleton\widgets\grids\columns\ButtonColumn;
use davidhirtz\yii2\skeleton\widgets\grids\columns\buttons\DraggableSortGridButton;
use davidhirtz\yii2\skeleton\widgets\grids\columns\buttons\ViewGridButton;
use davidhirtz\yii2\skeleton\widgets\grids\columns\Column;
use davidhirtz\yii2\skeleton\widgets\grids\columns\DataColumn;
use davidhirtz\yii2\skeleton\widgets\grids\columns\RelativeTimeColumn;
use davidhirtz\yii2\skeleton\widgets\grids\GridView;
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
