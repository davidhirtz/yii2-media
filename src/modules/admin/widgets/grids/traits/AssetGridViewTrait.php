<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\grids\traits;

use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use davidhirtz\yii2\skeleton\html\Button;
use davidhirtz\yii2\skeleton\widgets\grids\columns\buttons\DeleteGridButton;
use davidhirtz\yii2\skeleton\widgets\grids\columns\Column;
use davidhirtz\yii2\skeleton\widgets\grids\columns\DataColumn;
use Stringable;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\ExpressionInterface;

trait AssetGridViewTrait
{
    /**
     * @var int|ExpressionInterface|null the maximum number of assets loaded for `$parent`
     */
    public int|ExpressionInterface|null $maxAssetCount = 100;

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
            ->label(Yii::t('media', 'Are you sure you want to remove this asset?'))
            ->url(['asset/delete', 'id' => $model->id]);
    }

    protected function getFileUpdateButton(ActiveRecord&AssetInterface $asset): Stringable
    {
        return Button::make()
            ->secondary()
            ->icon('image')
            ->href(['file/update', 'id' => $asset->file_id])
            ->tooltip(Yii::t('media', 'Edit File'))
            ->addClass('d-none d-md-block')
            ->target('_blank');
    }
}
