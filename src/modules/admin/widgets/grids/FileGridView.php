<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids;

use Hirtz\Media\Models\collections\FolderCollection;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Models\interfaces\AssetParentInterface;
use Hirtz\Media\Modules\Admin\Data\FileActiveDataProvider;
use Hirtz\Media\Modules\Admin\Widgets\Grids\Columns\FileThumbnailColumn;
use Hirtz\Media\Modules\Admin\Widgets\Grids\Traits\FileGridViewTrait;
use Hirtz\Media\modules\ModuleTrait;
use Hirtz\Skeleton\Helpers\ArrayHelper;
use Hirtz\Skeleton\Helpers\Html;
use Hirtz\Skeleton\Html\A;
use Hirtz\Skeleton\Html\Button;
use Hirtz\Skeleton\Html\Div;
use Hirtz\Skeleton\Widgets\Grids\Columns\BadgeColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\ButtonColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\Buttons\DeleteGridButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Buttons\ViewGridButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Column;
use Hirtz\Skeleton\Widgets\Grids\Columns\DataColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\RelativeTimeColumn;
use Hirtz\Skeleton\Widgets\Grids\GridView;
use Hirtz\Skeleton\Widgets\Grids\Toolbars\FilterDropdown;
use Override;
use Stringable;
use Yii;
use yii\db\ActiveRecordInterface;
use yii\helpers\Url;

/**
 * @extends GridView<File>
 * @property FileActiveDataProvider $provider
 */
class FileGridView extends GridView
{
    use FileGridViewTrait;
    use ModuleTrait;

    protected ?Folder $folder = null;
    protected ?AssetParentInterface $parent = null;

    public function folder(?Folder $folder): static
    {
        $this->folder = $folder;
        return $this;
    }

    public function parent(?AssetParentInterface $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    #[Override]
    protected function configure(): void
    {
        $this->model ??= File::instance();
        $this->folder ??= $this->provider->folder;

        $this->attributes['id'] ??= 'files';

        if ($this->parent) {
            $fileIds = array_map(intval(...), array_column($this->parent->assets, 'file_id'));

            $this->rowAttributes = fn (File $file) => [
                'class' => in_array($file->id, $fileIds, true) ? 'is-selected' : null,
            ];
        }

        $this->header ??= [
            $this->getFolderDropdown(),
            $this->search->getToolbarItem(),
        ];

        $this->columns ??= [
            $this->getThumbnailColumn(),
            $this->getNameColumn(),
            $this->getFilenameColumn(),
            $this->getAssetCountColumn(),
            $this->getAltTextColumn(),
            $this->getUpdatedAtColumn(),
            $this->getButtonsColumn(),
        ];

        $this->footer ??= [
            ...$this->getFooterButtons(),
        ];

        parent::configure();
    }

    protected function getFolderDropdown(): ?FilterDropdown
    {
        $items = $this->getFolderDropdownItems();

        return count($items) > 1
            ? FilterDropdown::make()
                ->items($items)
                ->param('folder')
                ->label(Yii::t('media', 'Folders'))
            : null;
    }

    protected function getFolderDropdownItems(): array
    {
        return ArrayHelper::getColumn(FolderCollection::getAll(), 'name');
    }

    protected function getFooterButtons(): array
    {
        if (!Yii::$app->getUser()->can(File::AUTH_FILE_CREATE, ['folder' => $this->folder])) {
            return [];
        }

        return [
            $this->getFileUploadButton(),
            $this->getFileImportButton(),
        ];
    }

    protected function getThumbnailColumn(): Column
    {
        return FileThumbnailColumn::make()
            ->url(fn (File $file) => $this->getRoute($file));
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
            ->href($this->getRoute($file))
            ->class('strong')
            ->content(Html::markKeywords(Html::encode($file->name), $this->search->getKeywords()));

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
            ->content(fn (File $file): string => Html::markKeywords(Html::encode($file->getFilename()), $this->search->getKeywords()))
            ->hiddenForSmallDevices();
    }

    protected function getAssetCountColumn(): Column
    {
        return BadgeColumn::make()
            ->label(Yii::t('media', 'Assets'))
            ->content(fn (File $file) => (string)$file->getRelatedModelCount())
            ->url(fn (File $file) => $this->getRoute($file, ['#' => 'assets']));
    }

    protected function getAltTextColumn(): Column
    {
        return DataColumn::make()
            ->property($this->model->getI18nAttributeName('alt_text'))
            ->content($this->getAltTextColumnContent(...))
            ->hiddenForSmallDevices()
            ->centered();
    }

    protected function getAltTextColumnContent(File $file): string|Stringable
    {
        if (!$file->getI18nAttribute('alt_text')) {
            return '';
        }

        return A::make()
            ->href($this->getRoute($file))
            ->icon('check')
            ->addClass('text-success');
    }

    protected function getUpdatedAtColumn(): Column
    {
        return RelativeTimeColumn::make()
            ->property('updated_at')
            ->hiddenForSmallDevices();
    }

    protected function getButtonsColumn(): Column
    {
        return ButtonColumn::make()
            ->content($this->getButtonsColumnContent(...));
    }

    protected function getButtonsColumnContent(File $file): array
    {
        if ($this->parent) {
            $route = [
                'create',
                $this->parent->getParamName() => $this->parent->getPrimaryKey(),
                'file' => $file->id,
            ];

            return [
                Button::make()
                    ->secondary()
                    ->icon('image')
                    ->href(['/admin/file/update', 'id' => $file->id])
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

    protected function getFileUploadRoute(): array
    {
        return [
            'create',
            'folder' => $this->folder?->id,
            ...$this->parent
                ? [strtolower($this->parent->formName()) => $this->parent->getPrimaryKey()]
                : []
        ];
    }

    #[Override]
    protected function getRoute(ActiveRecordInterface $model, array $params = []): array|false
    {
        return ['/admin/file/update', 'id' => $model->id, ...$params];
    }
}
