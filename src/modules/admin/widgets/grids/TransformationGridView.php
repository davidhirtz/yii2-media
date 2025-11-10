<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\grids;

use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Transformation;
use davidhirtz\yii2\media\modules\admin\widgets\grids\columns\FileThumbnailColumn;
use davidhirtz\yii2\media\modules\admin\widgets\grids\columns\Thumbnail;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\html\Icon;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grids\GridView;
use davidhirtz\yii2\timeago\TimeagoColumn;
use Override;
use Yii;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;

/**
 * @extends GridView<Transformation>
 * @property ActiveDataProvider|ArrayDataProvider|null $dataProvider
 */
class TransformationGridView extends GridView
{
    use ModuleTrait;

    public File $file;
    public $layout = '{items}{footer}';

    #[Override]
    public function init(): void
    {
        if ($this->dataProvider === null) {
            $this->dataProvider = new ArrayDataProvider([
                'allModels' => $this->file->getTransformations()
                    ->orderBy(['width' => SORT_DESC, 'size' => SORT_DESC])
                    ->indexBy('id')
                    ->all(),
                'pagination' => false,
                'sort' => false,
            ]);

            $this->setModel(Transformation::instance());
        }

        if (!$this->columns) {
            $this->columns = [
                $this->thumbnailColumn(),
                $this->nameColumn(),
                $this->dimensionsColumn(),
                $this->sizeColumn(),
                $this->createdAtColumn(),
                $this->buttonsColumn(),
            ];
        }

        parent::init();
    }

    public function thumbnailColumn(): array
    {
        return [
            'class' => FileThumbnailColumn::class,
            'content' => fn (Transformation $transformation) => Thumbnail::widget(['file' => $transformation->file]),
            'route' => fn (Transformation $transformation) => $transformation->getFileUrl(),
            'wrapperOptions' => [
                'target' => '_blank',
            ],
        ];
    }

    public function nameColumn(): array
    {
        return [
            'attribute' => 'name',
            'content' => function (Transformation $transformation) {
                $content = $transformation->name . ($transformation->isWebp() ? ' (webp)' : '');
                return Html::tag('strong', $content);
            }
        ];
    }

    public function dimensionsColumn(): array
    {
        return [
            'attribute' => 'dimensions',
            'visible' => $this->file->hasDimensions(),
            'content' => fn (Transformation $transformation): string => $transformation->width && $transformation->height ? ($transformation->width . ' x ' . $transformation->height) : ''
        ];
    }

    public function sizeColumn(): array
    {
        return [
            'attribute' => 'size',
            'content' => fn (Transformation $transformation) => Yii::$app->getFormatter()->asShortSize($transformation->size)
        ];
    }

    public function createdAtColumn(): array
    {
        return [
            'attribute' => 'created_at',
            'class' => TimeagoColumn::class,
        ];
    }

    public function buttonsColumn(): array
    {
        return [
            'contentOptions' => ['class' => 'text-end'],
            'content' => fn (Transformation $transformation) => Html::buttons(Html::a((string)Icon::tag('trash'), ['transformation/delete', 'id' => $transformation->id], [
                'class' => 'btn btn-danger',
                'data-method' => 'post',
            ]))
        ];
    }

    public function isSortable(): bool
    {
        return false;
    }
}
