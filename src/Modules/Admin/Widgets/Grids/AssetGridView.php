<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Modules\Admin\Controllers\Traits\AssetControllerTrait;
use Hirtz\Media\Modules\Admin\Data\AssetArrayDataProvider;
use Hirtz\Media\Modules\Admin\Widgets\Grids\Columns\AssetThumbnailColumn;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Html\A;
use Hirtz\Skeleton\Html\Div;
use Hirtz\Skeleton\Widgets\Grids\Columns\ButtonColumn;
use Hirtz\Skeleton\Widgets\Buttons\DraggableSortButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Buttons\ViewGridButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Column;
use Hirtz\Skeleton\Widgets\Grids\Columns\DataColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\StatusIconColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\TypeColumn;
use Hirtz\Skeleton\Widgets\Grids\GridSummary;
use Hirtz\Skeleton\Widgets\Grids\GridView;
use Hirtz\Skeleton\Widgets\Grids\Traits\SelectionTrait;
use Override;
use Stringable;
use Yii;

/**
 * Every row belongs to the provider's model, so the order route and the permission arguments are taken from it.
 *
 * @template T of Asset
 * @extends GridView<T>
 *
 * @property AssetArrayDataProvider $provider
 */
class AssetGridView extends GridView
{
    use ModuleTrait;
    use SelectionTrait;

    final public const string ID = 'asset-grid-view';

    #[Override]
    protected function configure(): void
    {
        $this->attributes['id'] ??= self::ID;

        $model = $this->provider->model;
        $this->orderRoute = $model->getAssetClass()::getAdminOrderRoute($model);

        $this->configureSelection();

        $this->columns ??= [
            $this->getCheckboxColumn(),
            $this->getStatusColumn(),
            $this->getThumbnailColumn(),
            $this->getTypeColumn(),
            $this->getNameColumn(),
            $this->getDimensionsColumn(),
            $this->getButtonColumn(),
        ];

        parent::configure();
    }

    /**
     * The rows share one model, so the first asset answers the permission for the whole selection.
     */
    protected function canDeleteSelection(): bool
    {
        $assets = $this->provider->getModels();
        return $assets && $this->can(reset($assets));
    }

    protected function getDeleteSelectionLabel(): string
    {
        return Yii::t('media', 'ASSET_BUTTON_REMOVE_SELECTED');
    }

    protected function getDeleteSelectionMessage(): string
    {
        return Yii::t('media', 'ASSET_CONFIRM_DELETE_SELECTED');
    }

    /**
     * @see AssetControllerTrait::deleteAssets()
     */
    protected function getDeleteSelectionRoute(): array
    {
        $model = $this->provider->model;
        return $model->getAssetClass()::getAdminDeleteAllRoute($model);
    }

    protected function getDimensionsColumn(): ?Column
    {
        return DataColumn::make()
            ->property('dimensions')
            ->content($this->getDimensionsColumnContent(...));
    }

    protected function getDimensionsColumnContent(Asset $asset): string
    {
        return $asset->file->hasDimensions() ? $asset->file->getDimensions() : '-';
    }

    #[Override]
    protected function getSummary(): ?GridSummary
    {
        return parent::getSummary()
            ->emptyMessage(Yii::t('media', 'ASSET_GRID_SUMMARY_EMPTY'))
            ->visible(fn (): bool => $this->provider->getCount() === 0);
    }

    protected function getStatusColumn(): ?Column
    {
        return StatusIconColumn::make()
            ->enableUpdate(fn (Asset $asset): bool => $this->enableStatusUpdate && $this->can($asset));
    }

    protected function getTypeColumn(): ?Column
    {
        return TypeColumn::make()
            ->url(fn (Asset $asset) => $asset->getAdminRoute());
    }

    protected function getThumbnailColumn(): ?Column
    {
        return AssetThumbnailColumn::make()
            ->url(fn (Asset $asset): ?array => $this->can($asset) ? $asset->getAdminRoute() ?: null : null);
    }

    protected function getNameColumn(): ?Column
    {
        return DataColumn::make()
            ->property(Asset::instance()->getI18nAttributeName('name'))
            ->content($this->getNameColumnContent(...));
    }

    protected function getNameColumnContent(Asset $asset): ?Stringable
    {
        $name = $asset->getVisibleAttribute('name');

        $content = $name
            ? Div::make()
                ->class('strong')
                ->text($name)
            : Div::make()
                ->class('text-muted')
                ->text($asset->file->name);

        return $this->can($asset)
            ? A::make()
                ->content($content)
                ->href($asset->getAdminRoute() ?: null)
            : $content;
    }

    protected function getButtonColumn(): ?Column
    {
        return ButtonColumn::make()
            ->content($this->getButtonColumnContent(...));
    }

    /**
     * @return list<Stringable>
     */
    protected function getButtonColumnContent(Asset $asset): array
    {
        $buttons = [];

        if ($this->isSortable() && $this->provider->getCount() > 1 && $this->can($asset)) {
            $buttons[] = DraggableSortButton::make();
        }

        if ($this->can($asset)) {
            $buttons[] = ViewGridButton::make()
                ->model($asset);
        }

        return $buttons;
    }

    protected function can(Asset $asset): bool
    {
        return $this->webuser->can($asset->getPermissionName());
    }
}
