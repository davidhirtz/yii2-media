<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids\Traits;

use Hirtz\Media\Models\Interfaces\AssetInterface;
use Hirtz\Media\Models\Interfaces\AssetParentInterface;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Widgets\Buttons\Button;
use Hirtz\Skeleton\Widgets\Grids\Columns\Buttons\DeleteGridButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Column;
use Hirtz\Skeleton\Widgets\Grids\Columns\DataColumn;
use Stringable;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\ExpressionInterface;

trait AssetGridViewTrait
{
    protected AssetParentInterface $parent;

    /**
     * @var int|ExpressionInterface|null the maximum number of assets loaded for `$parent`
     */
    protected int|ExpressionInterface|null $maxAssetCount = 100;

    public function parent(AssetParentInterface $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    public function maxAssetCount(int|ExpressionInterface|null $maxAssetCount): static
    {
        $this->maxAssetCount = $maxAssetCount;
        return $this;
    }

    protected function getDimensionsColumn(): ?Column
    {
        return DataColumn::make()
            ->property('dimensions')
            ->content($this->getDimensionsColumnContent(...));
    }

    protected function getDimensionsColumnContent(AssetInterface $asset): string
    {
        return $asset->file->hasDimensions() ? $asset->file->getDimensions() : '-';
    }

    protected function getAssetActiveDataProvider(): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => $this->getParentAssetQuery(),
            'pagination' => false,
            'sort' => false,
        ]);
    }

    protected function getParentAssetQuery(): ActiveQuery
    {
        return $this->parent->getAssets()
            ->with('file')
            ->limit($this->maxAssetCount);
    }

    protected function getDeleteButton(ActiveRecord&AssetInterface $model): Stringable
    {
        return DeleteGridButton::make()
            ->model($model)
            ->title(Yii::t('media', 'Are you sure you want to remove this asset?'))
            ->url(['asset/delete', 'id' => $model->id]);
    }

    protected function getFileUpdateButton(ActiveRecord&AssetInterface $asset): Stringable
    {
        return Button::make()
            ->secondary()
            ->icon('image')
            ->url(['file/update', 'id' => $asset->file_id])
            ->tooltip(Yii::t('media', 'Edit File'))
            ->addClass('d-none d-md-block')
            ->target('_blank');
    }
}
