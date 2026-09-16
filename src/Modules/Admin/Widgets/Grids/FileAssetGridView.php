<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Traits\FilePropertyTrait;
use Hirtz\Skeleton\Widgets\Grids\Columns\ButtonColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\Buttons\DeleteGridButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Buttons\ViewGridButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Column;
use Hirtz\Skeleton\Widgets\Grids\Columns\LinkColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\RelativeTimeColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\StatusIconColumn;
use Hirtz\Skeleton\Widgets\Grids\GridSummary;
use Hirtz\Skeleton\Widgets\Grids\GridView;
use Override;
use Stringable;
use Yii;
use yii\data\ActiveDataProvider;

/**
 * Every asset of one file, across the subclasses.
 *
 * @extends GridView<Asset>
 */
class FileAssetGridView extends GridView
{
    use FilePropertyTrait;

    protected string $layout = '{summary}{items}{pager}';

    #[Override]
    protected function configure(): void
    {
        $this->attributes['id'] ??= 'file-asset-grid-view';

        $this->provider ??= new ActiveDataProvider([
            'query' => Asset::find()
                ->andWhere(['file_id' => $this->file->id])
                ->orderBy(['updated_at' => SORT_DESC]),
        ]);

        $assets = $this->provider->getModels();
        Asset::populateModelRelations($assets);

        foreach ($assets as $asset) {
            $asset->populateFileRelation($this->file);
        }

        $this->columns ??= [
            $this->getStatusColumn(),
            $this->getModelColumn(),
            $this->getUpdatedAtColumn(),
            $this->getButtonColumn(),
        ];

        parent::configure();
    }

    #[Override]
    protected function getSummary(): ?GridSummary
    {
        return parent::getSummary()
            ->emptyMessage(Yii::t('media', 'FILE_ASSET_GRID_SUMMARY_EMPTY'))
            ->visible(fn (): bool => $this->provider->getCount() === 0);
    }

    protected function getStatusColumn(): ?Column
    {
        // The rows are assets of every subclass, so the permission is the row's own.
        return StatusIconColumn::make()
            ->enableUpdate(fn (Asset $asset): bool => $this->enableStatusUpdate && $this->can($asset));
    }

    protected function getModelColumn(): ?Column
    {
        return LinkColumn::make()
            ->property('model_class')
            ->title(Yii::t('media', 'ASSET_MODEL_LABEL'))
            ->value(fn (Asset $asset) => $asset->model->getAdminName())
            ->url(fn (Asset $asset) => $asset->model->getAdminRoute());
    }

    protected function getUpdatedAtColumn(): ?Column
    {
        return RelativeTimeColumn::make()
            ->property('updated_at');
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

        if ($this->can($asset)) {
            $buttons[] = ViewGridButton::make()
                ->url($asset->getAdminRoute() ?: null);
        }

        if ($this->can($asset)) {
            $buttons[] = DeleteGridButton::make()
                ->model($asset)
                ->title(Yii::t('media', 'COMMON_REMOVE_TITLE'))
                ->url(['delete', 'id' => $asset->id]);
        }

        return $buttons;
    }

    protected function can(Asset $asset): bool
    {
        return $this->webuser->can($asset->getPermissionName());
    }
}
