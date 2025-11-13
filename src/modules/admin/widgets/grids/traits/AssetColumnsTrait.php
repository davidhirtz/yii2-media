<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\grids\traits;

use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use davidhirtz\yii2\skeleton\html\Button;
use davidhirtz\yii2\skeleton\widgets\grids\buttons\DeleteButton;
use Stringable;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\ExpressionInterface;

trait AssetColumnsTrait
{
    /**
     * @var int|ExpressionInterface|null the maximum number of assets loaded for `$parent`
     */
    public int|ExpressionInterface|null $maxAssetCount = 100;

    public function dimensionsColumn(): array
    {
        return [
            'attribute' => $this->getModel()->getAttribute('dimensions'),
            'content' => fn (AssetInterface $asset) => $asset->file->hasDimensions() ? $asset->file->getDimensions() : '-'
        ];
    }

    public function getAssetActiveDataProvider(): ActiveDataProvider
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
        return Yii::createObject(DeleteButton::class, [
            $model,
            ['file/delete', 'id' => $model->file_id],
            Yii::t('media', 'Are you sure you want to remove this asset?'),
        ]);
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
