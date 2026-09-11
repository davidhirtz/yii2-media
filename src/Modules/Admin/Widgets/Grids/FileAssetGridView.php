<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Modules\Admin\Widgets\Grids\Traits\AssetGridViewTrait;
use Hirtz\Media\Traits\FilePropertyTrait;
use Hirtz\Skeleton\I18n\Lang;
use Hirtz\Skeleton\Models\Interfaces\TrailModelInterface;
use Hirtz\Skeleton\Widgets\Grids\Columns\ButtonColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\Buttons\ViewGridButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Column;
use Hirtz\Skeleton\Widgets\Grids\Columns\LinkColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\RelativeTimeColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\StatusIconColumn;
use Hirtz\Skeleton\Widgets\Grids\GridView;
use Override;
use ReflectionClass;
use Stringable;
use yii\data\ActiveDataProvider;

/**
 * Every asset of one file, across the subclasses.
 *
 * @extends GridView<Asset>
 */
class FileAssetGridView extends GridView
{
    use AssetGridViewTrait;
    use FilePropertyTrait;

    protected string $layout = '{items}{pager}';

    #[Override]
    protected function configure(): void
    {
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
            $this->getNameColumn(),
            $this->getUpdatedAtColumn(),
            $this->getButtonColumn(),
        ];

        parent::configure();
    }

    protected function getStatusColumn(): ?Column
    {
        return StatusIconColumn::make();
    }

    protected function getModelColumn(): ?Column
    {
        return LinkColumn::make()
            ->property('model_class')
            ->title(Lang::t('media', 'ASSET_MODEL_LABEL'))
            ->content($this->getModelColumnContent(...))
            ->url(fn (Asset $asset) => $asset->model->getAdminRoute());
    }

    protected function getModelColumnContent(Asset $asset): string
    {
        $model = $asset->model;

        return $model instanceof TrailModelInterface
            ? $model->getTrailModelName()
            : (new ReflectionClass($model))->getShortName() . ' ' . $model->id;
    }

    protected function getNameColumn(): ?Column
    {
        return LinkColumn::make()
            ->property(Asset::instance()->getI18nAttributeName('name'))
            ->content(fn (Asset $asset): string => (string)$asset->getI18nAttribute('name'))
            ->url(fn (Asset $asset) => $asset->getAdminRoute());
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

        if ($this->can('update', $asset)) {
            $buttons[] = ViewGridButton::make()
                ->url($asset->getAdminRoute());
        }

        if ($this->can('delete', $asset)) {
            $buttons[] = $this->getDeleteButton($asset)
                ->url(['delete', 'id' => $asset->id]);
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
