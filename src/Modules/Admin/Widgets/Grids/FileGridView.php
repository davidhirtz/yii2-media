<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids;

use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Media\Modules\Admin\Data\FileActiveDataProvider;
use Hirtz\Media\Modules\Admin\Widgets\Grids\Columns\FileThumbnailColumn;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Helpers\ArrayHelper;
use Hirtz\Skeleton\Helpers\Html;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Html\A;
use Hirtz\Skeleton\Html\Div;
use Hirtz\Skeleton\Widgets\Buttons\Button;
use Hirtz\Skeleton\Widgets\Grids\Columns\BadgeColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\ButtonColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\Buttons\DeleteGridButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Buttons\ViewGridButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Column;
use Hirtz\Skeleton\Widgets\Grids\Columns\DataColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\RelativeTimeColumn;
use Hirtz\Skeleton\Widgets\Grids\GridView;
use Hirtz\Skeleton\Widgets\Grids\Toolbars\FilterDropdown;
use Hirtz\Skeleton\Widgets\Link;
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

    final public const string ID = 'files';

    protected ?Folder $folder = null;

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
            $fileIds = array_map(intval(...), array_column($this->model->assets, 'file_id'));

            $this->rowAttributes = fn (File $file) => [
                'class' => in_array($file->id, $fileIds, true) ? 'is-selected' : null,
            ];
        }

        $this->header ??= [
            $this->getFolderDropdown(),
            $this->getSearchInput(),
        ];

        $this->columns ??= [
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

    protected function getFolderDropdownItems(): array
    {
        return ArrayHelper::getColumn(FolderCollection::getAll(), 'name');
    }

    protected function getThumbnailColumn(): Column
    {
        return FileThumbnailColumn::make()
            ->url(fn (File $file) => $file->getAdminRoute());
    }

    protected function getNameColumn(): Column
    {
        return DataColumn::make()
            ->property('name')
            ->content($this->getNameColumnContent(...));
    }

    protected function getNameColumnContent(File $file): string|Stringable
    {
        $html = A::make()
            ->href($file->getAdminRoute())
            ->class('strong')
            ->content($this->search->markKeywords($file->name));

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
            ->url(fn (File $file) => ['/admin/media/asset/index', 'file' => $file->id]);
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

        return Link::make()
            ->class('text-success')
            ->href($file->getAdminRoute())
            ->icon('check');
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

    protected function getButtonColumnContent(File $file): array
    {
        if ($this->model) {
            $route = [
                ...$this->model->getAssetClass()::getAdminCreateRoute($this->model),
                'file' => $file->id,
            ];

            return [
                Button::make()
                    ->secondary()
                    ->icon('image')
                    ->url(['/admin/media/file/update', 'id' => $file->id])
                    ->addClass('d-none d-md-block'),
                Button::make()
                    ->primary()
                    ->icon('plus')
                    ->post($route),
            ];
        }

        return [
            ViewGridButton::make()
                ->model($file),
            DeleteGridButton::make()
                ->model($file),
        ];
    }
}
