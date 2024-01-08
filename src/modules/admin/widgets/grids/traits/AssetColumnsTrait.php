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
use yii\db\ActiveRecordInterface;
use yii\db\ExpressionInterface;
use yii\helpers\Url;

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

    /**
     * @param AssetInterface $model
     */
    protected function getDeleteButton(ActiveRecordInterface $model): string
    {
        $options = [
            'class' => 'btn btn-danger btn-delete-asset d-none d-md-inline-block',
            'data-confirm' => Yii::t('cms', 'Are you sure you want to remove this asset?'),
            'data-target' => '#' . $this->getRowId($model),
            'data-ajax' => 'remove',
        ];


        if (Yii::$app->getUser()->can('fileDelete', ['file' => $model->file])) {
            $options['data-delete-message'] = Yii::t('cms', 'Permanently delete related files');
            $options['data-delete-url'] = Url::to(['file/delete', 'id' => $model->file_id]);
        }

        return Html::a(Icon::tag('trash'), $this->getDeleteRoute($model), $options);
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
