<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Media\Modules\Admin\Data\AssetArrayDataProvider;
use Hirtz\Media\Modules\Admin\Widgets\Grids\Columns\AssetThumbnailColumn;
use Hirtz\Media\Modules\Admin\Widgets\Grids\Traits\AssetGridViewTrait;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Html\A;
use Hirtz\Skeleton\Html\Div;
use Hirtz\Skeleton\Widgets\Grids\Columns\ButtonColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\Buttons\DraggableSortGridButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Buttons\ViewGridButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Column;
use Hirtz\Skeleton\Widgets\Grids\Columns\DataColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\StatusIconColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\TypeColumn;
use Hirtz\Skeleton\Widgets\Grids\GridSummary;
use Hirtz\Skeleton\Widgets\Grids\GridView;
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
    use AssetGridViewTrait;
    use ModuleTrait;

    final public const string ID = 'asset-grid-view';

    #[Override]
    protected function configure(): void
    {
        $this->attributes['id'] ??= self::ID;

        $this->columns ??= [
            $this->getStatusColumn(),
            $this->getThumbnailColumn(),
            $this->getTypeColumn(),
            $this->getNameColumn(),
            $this->getDimensionsColumn(),
            $this->getButtonColumn(),
        ];

        $model = $this->provider->model;
        $this->orderRoute = $model->getAssetClass()::getAdminOrderRoute($model);

        parent::configure();
    }

    #[Override]
    protected function getSummary(): ?GridSummary
    {
        return parent::getSummary()
            ->message(Yii::t('media', 'ASSET_GRID_SUMMARY_EMPTY'))
            ->visible(fn (): bool => $this->provider->getCount() === 0);
    }

    protected function getStatusColumn(): ?Column
    {
        return StatusIconColumn::make();
    }

    protected function getTypeColumn(): ?Column
    {
        return TypeColumn::make()
            ->url(fn (Asset $asset) => $asset->getAdminRoute());
    }

    protected function getThumbnailColumn(): ?Column
    {
        return AssetThumbnailColumn::make()
            ->url(fn (Asset $asset): ?array => $this->can('update', $asset) ? $asset->getAdminRoute() : null);
    }

    protected function getNameColumn(): ?Column
    {
        return DataColumn::make()
            ->property(Asset::instance()->getI18nAttributeName('name'))
            ->content($this->getNameColumnContent(...));
    }

    protected function getNameColumnContent(Asset $asset): ?Stringable
    {
        $name = $asset->getI18nAttribute('name');

        $content = $name
            ? Div::make()
                ->class('strong')
                ->text($name)
            : Div::make()
                ->class('text-muted')
                ->text($asset->file->name);

        return $this->can('update', $asset)
            ? A::make()
                ->content($content)
                ->href($asset->getAdminRoute())
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

        if ($this->isSortable() && $this->provider->getCount() > 1 && $this->can('order', $asset)) {
            $buttons[] = DraggableSortGridButton::make();
        }

        if ($this->webuser->can(File::AUTH_FILE_UPDATE, ['file' => $asset->file])) {
            $buttons[] = $this->getFileUpdateButton($asset);
        }

        if ($this->can('update', $asset)) {
            $buttons[] = ViewGridButton::make()
                ->model($asset);
        }

        if ($this->can('delete', $asset)) {
            $buttons[] = $this->getDeleteButton($asset);
        }

        return $buttons;
    }

    /**
     * @param 'create'|'delete'|'order'|'update' $action
     */
    protected function can(string $action, Asset $asset): bool
    {
        $model = $asset->model;

        return $this->webuser->can($asset->getPermissionName($action), [
            'asset' => $asset,
            $model->getParamName() => $model,
        ]);
    }
}
