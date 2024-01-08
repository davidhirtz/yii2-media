<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\grids\traits;

use davidhirtz\yii2\media\assets\AdminAsset;
use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use davidhirtz\yii2\media\models\interfaces\AssetParentInterface;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grids\GridView;
use davidhirtz\yii2\skeleton\widgets\fontawesome\Icon;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\ExpressionInterface;

/**
 * @mixin GridView
 */
trait AssetColumnsTrait
{
    public ?AssetParentInterface $parent = null;

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

    protected function getFileUpdateButton(AssetInterface $asset): string
    {
        return Html::a(Icon::tag('image'), ['file/update', 'id' => $asset->file_id], [
            'class' => 'btn btn-secondary d-none d-md-inline-block',
            'title' => Yii::t('media', 'Edit File'),
            'data-toggle' => 'tooltip',
            'target' => '_blank',
        ]);
    }

    protected function registerAssetClientScripts(): void
    {
        $view = $this->getView();
        AdminAsset::register($view);

        $view->registerJs('Skeleton.deleteFilesWithAssets();', $view::POS_READY, 'deleteFilesWithAssets');
        $view->registerJs('Skeleton.mediaFileImport();', $view::POS_READY, 'mediaFileImport');
    }
}
