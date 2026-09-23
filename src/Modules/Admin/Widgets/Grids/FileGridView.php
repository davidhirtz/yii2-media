<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Media\Modules\Admin\Controllers\FileController;
use Hirtz\Media\Modules\Admin\Data\FileActiveDataProvider;
use Hirtz\Media\Modules\Admin\Widgets\Grids\Columns\FileThumbnailColumn;
use Hirtz\Media\Modules\Admin\Widgets\Navs\AssetSubmenuItem;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Helpers\ArrayHelper;
use Hirtz\Skeleton\Helpers\Html;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Html\A;
use Hirtz\Skeleton\Html\Div;
use Hirtz\Skeleton\Html\Label;
use Hirtz\Skeleton\Html\Option;
use Hirtz\Skeleton\Html\Select;
use Hirtz\Skeleton\Widgets\Buttons\Button;
use Hirtz\Skeleton\Widgets\Grids\Columns\BadgeColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\ButtonColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\Buttons\DeleteGridButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Buttons\ViewGridButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Column;
use Hirtz\Skeleton\Widgets\Grids\Columns\DataColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\RelativeTimeColumn;
use Hirtz\Skeleton\Widgets\Grids\GridView;
use Hirtz\Skeleton\Widgets\Grids\Traits\SelectionTrait;
use Hirtz\Skeleton\Widgets\Grids\Toolbars\FilterDropdown;
use Hirtz\Skeleton\Widgets\Grids\Toolbars\GridToolbarItem;
use Hirtz\Skeleton\Widgets\Icon;
use Hirtz\Skeleton\Widgets\Link;
use Hirtz\Skeleton\Widgets\Modal;
use Hirtz\Skeleton\Widgets\Traits\ModelTrait;
use Override;
use Stringable;
use Yii;

/**
 * @extends GridView<File>
 * @property FileActiveDataProvider $provider
 */
class FileGridView extends GridView
{
    /**
     * @use ModelTrait<(ActiveRecord&AssetModelInterface)|null>
     */
    use ModelTrait;
    use ModuleTrait;
    use SelectionTrait;

    final public const string ID = 'files';

    public bool $showDeleteButton = false;

    protected ?Asset $asset = null;
    protected ?Folder $folder = null;

    /**
     * @var array<int, Asset> the model's assets, keyed by file id — what makes a file already picked
     */
    protected array $assetsByFileId = [];

    /**
     * @param Asset|null $asset the asset whose file the grid picks a replacement for, rather than adding an asset
     */
    public function asset(?Asset $asset): static
    {
        $this->asset = $asset;
        return $this;
    }

    public function folder(?Folder $folder): static
    {
        $this->folder = $folder;
        return $this;
    }

    #[Override]
    protected function configure(): void
    {
        $this->folder ??= $this->provider->folder;

        $this->attributes['id'] ??= self::ID;

        if ($this->model) {
            foreach ($this->model->assets as $asset) {
                $this->assetsByFileId[(int)$asset->file_id] ??= $asset;
            }

            $this->rowAttributes = fn (File $file) => [
                'class' => $this->isSelected($file) ? 'is-selected' : null,
            ];
        }

        $this->header ??= [
            $this->getFolderDropdown(),
            $this->getSearchInput(),
        ];

        $this->configureSelection();

        $this->columns ??= [
            $this->getCheckboxColumn(),
            $this->getThumbnailColumn(),
            $this->getNameColumn(),
            $this->getFilenameColumn(),
            $this->getAssetCountColumn(),
            $this->getAltTextColumn(),
            $this->getUpdatedAtColumn(),
            $this->getButtonColumn(),
        ];

        parent::configure();
    }

    protected function canDeleteSelection(): bool
    {
        return !$this->isPicker() && $this->webuser->can(File::AUTH_FILE);
    }

    /**
     * @return list<Stringable|null>
     */
    protected function getSelectionItems(): array
    {
        return [$this->getMoveSelectionButton(), $this->getDeleteSelectionButton()];
    }

    /**
     * A project may have dozens of folders, so the target is picked from a select in a modal rather than from a
     * dropdown of one item per folder. With no second folder there is nowhere to move the selection to.
     *
     * @see FileController::actionMoveAll()
     */
    protected function getMoveSelectionButton(): ?Stringable
    {
        if (count(FolderCollection::getAll()) < 2) {
            return null;
        }

        $select = $this->getFolderSelect();

        $modal = Modal::make()
            ->title(Yii::t('media', 'FILE_BUTTON_MOVE_SELECTED'))
            ->content(Label::make()
                ->class('form-label')
                ->text(Yii::t('media', 'FILE_FOLDER_ID_LABEL'))
                ->for($select->getId()), $select)
            ->footer(Button::make()
                ->primary()
                ->text(Yii::t('media', 'FILE_BUTTON_MOVE_SELECTED'))
                ->icon('folder-open')
                ->post(['/admin/media/file/move-all'])
                ->attribute('hx-include', "[data-check]:checked, #{$select->getId()}"));

        return GridToolbarItem::make()
            ->content(Button::make()
                ->primary()
                ->text(Yii::t('media', 'FILE_BUTTON_MOVE_SELECTED'))
                ->icon('folder-open')
                ->modal($modal));
    }

    protected function getDeleteSelectionLabel(): string
    {
        return Yii::t('media', 'FILE_BUTTON_DELETE_SELECTED');
    }

    protected function getDeleteSelectionMessage(): string
    {
        return Yii::t('media', 'FILE_CONFIRM_DELETE_SELECTED');
    }

    /**
     * @see FileController::actionDeleteAll()
     */
    protected function getDeleteSelectionRoute(): array
    {
        return ['/admin/media/file/delete-all'];
    }

    /**
     * The folder the grid is filtered to is the one the files are already in, so it is not offered as a target.
     */
    protected function getFolderSelect(): Select
    {
        $select = Select::make()
            ->class('input')
            ->name('folder');

        foreach (FolderCollection::getAll() as $folder) {
            if ($folder->id === $this->folder?->id) {
                continue;
            }

            $select->addOption(Option::make()
                ->label($folder->name)
                ->value((string)$folder->id));
        }

        return $select;
    }

    protected function getFolderDropdown(): ?FilterDropdown
    {
        $items = $this->getFolderDropdownItems();

        return count($items) > 1
            ? FilterDropdown::make()
                ->items($items)
                ->paramName('folder')
                ->label(Yii::t('media', 'COMMON_FOLDERS'))
            : null;
    }

    /**
     * @return array<int, string>
     */
    protected function getFolderDropdownItems(): array
    {
        return ArrayHelper::getColumn(FolderCollection::getAll(), 'name');
    }

    protected function getThumbnailColumn(): Column
    {
        return FileThumbnailColumn::make()
            ->url($this->getRecordUrl(...));
    }

    /**
     * A grid with a model is a list to pick a file *for* it rather than to navigate. A picker must not lead away
     * from itself — that cancels the flow the user is in — so its thumbnail, name and alt text check carry no
     * link, its asset count badge carries none either, and the file's own page is an external link button.
     */
    protected function isPicker(): bool
    {
        return $this->model !== null;
    }

    /**
     * Where the row's own links lead: the thumbnail, the name and the alt text check.
     *
     * @return array<array-key, mixed>|null
     */
    protected function getRecordUrl(File $file): ?array
    {
        return $this->isPicker() ? null : $file->getAdminRoute();
    }

    protected function getNameColumn(): Column
    {
        return DataColumn::make()
            ->property('name')
            ->content($this->getNameColumnContent(...));
    }

    protected function getNameColumnContent(File $file): string|Stringable
    {
        $url = $this->getRecordUrl($file);
        $name = $this->search->markKeywords($file->name);

        $html = $url
            ? A::make()->href($url)->class('strong')->content($name)
            : Div::make()->class('strong')->content($name);

        if (!$this->folder) {
            $folder = A::make()
                ->href(Url::current(['folder' => $file->folder_id, 'page' => 0]))
                ->text(Html::encode($file->folder->name));

            $html .= Div::make()
                ->addClass('d-none d-md-block small')
                ->content($folder);
        }

        return $html;
    }

    protected function getFilenameColumn(): Column
    {
        return DataColumn::make()
            ->property('filename')
            ->content(fn (File $file): string => $this->search->markKeywords($file->getFilename()))
            ->hiddenForSmallDevices();
    }

    protected function getAssetCountColumn(): Column
    {
        return BadgeColumn::make()
            ->title(Yii::t('media', 'COMMON_ASSETS'))
            ->value(fn (File $file) => (string)$file->asset_count)
            ->url($this->isPicker() ? null : fn (File $file) => ['/admin/media/asset/index', 'file' => $file->id]);
    }

    protected function getAltTextColumn(): Column
    {
        return DataColumn::make()
            ->property(File::instance()->getI18nAttributeName('alt_text'))
            ->content($this->getAltTextColumnContent(...))
            ->hiddenForSmallDevices()
            ->centered();
    }

    protected function getAltTextColumnContent(File $file): string|Stringable
    {
        if (!$file->getI18nAttribute('alt_text')) {
            return '';
        }

        $url = $this->getRecordUrl($file);

        return $url
            ? Link::make()->class('text-success')->href($url)->icon('check')
            : Icon::make()->name('check')->addClass('text-success');
    }

    protected function getUpdatedAtColumn(): Column
    {
        return RelativeTimeColumn::make()
            ->property('updated_at')
            ->hiddenForSmallDevices();
    }

    protected function getButtonColumn(): Column
    {
        return ButtonColumn::make()
            ->content($this->getButtonColumnContent(...));
    }

    /**
     * Whether the model already holds this file — the row it is on is marked, and its button removes it rather than
     * adding it a second time. While an asset's file is being replaced it is that asset's own file that is marked.
     */
    protected function isSelected(File $file): bool
    {
        return $this->asset
            ? (int)$this->asset->file_id === (int)$file->id
            : isset($this->assetsByFileId[(int)$file->id]);
    }

    /**
     * A model holds a file once, so the button is a toggle: add what is not there, remove what is. Replacing an
     * asset's file offers no button for a file the model already holds — its own, which would be a no-op, or
     * another asset's, which the uniqueness rule refuses.
     *
     * The toggle swaps the grid alone, so a click on the thirtieth row leaves the user on the thirtieth row, and
     * refreshes the submenu's asset count beside it. Replacing a file leads to the new asset's own page instead,
     * so that button keeps the body's whole-page swap.
     */
    protected function getPickerButton(File $file): ?Stringable
    {
        $assetClass = $this->model->getAssetClass();

        if ($this->asset) {
            return isset($this->assetsByFileId[(int)$file->id])
                ? null
                : Button::make()
                    ->primary()
                    ->icon('exchange-alt')
                    ->post([
                        ...$assetClass::getAdminCreateRoute($this->model),
                        'file' => $file->id,
                        'asset' => $this->asset->id,
                    ]);
        }

        $isSelected = isset($this->assetsByFileId[(int)$file->id]);

        $route = $isSelected
            ? $assetClass::getAdminRemoveRoute($this->model)
            : $assetClass::getAdminCreateRoute($this->model);

        return Button::make()
            ->primary()
            ->icon($isSelected ? 'ban' : 'plus')
            ->replace(
                [...$route, 'file' => $file->id, ...$this->getPickerFilterParams()],
                '#' . $this->getId(),
                '#' . AssetSubmenuItem::ID,
            );
    }

    /**
     * What the picker is narrowed to, carried into the toggle so the action can redirect back to the same list —
     * a user adding a dozen files must not be thrown back to every file in the library after the first.
     *
     * @return array<string, mixed>
     */
    protected function getPickerFilterParams(): array
    {
        return [
            'folder' => $this->folder?->id,
            'q' => $this->search->getValue() ?: null,
        ];
    }

    /**
     * @return list<Stringable>
     */
    protected function getButtonColumnContent(File $file): array
    {
        if ($this->model) {
            return array_filter([
                Button::make()
                    ->secondary()
                    ->icon('external-link-alt')
                    ->tooltip(Yii::t('media', 'COMMON_OPEN_ADMIN'))
                    ->url(['/admin/media/file/update', 'id' => $file->id])
                    ->target('_blank')
                    ->addClass('d-none d-md-block'),
                $this->getPickerButton($file),
            ]);
        }

        $buttons = [
            ViewGridButton::make()
                ->model($file),
        ];

        if ($this->showDeleteButton) {
            $buttons[] = DeleteGridButton::make()
                ->label(Yii::t('media', 'FILE_BUTTON_DELETE'))
                ->title(Yii::t('media', 'FILE_CONFIRM_DELETE'))
                ->model($file);
        }

        return $buttons;
    }
}
