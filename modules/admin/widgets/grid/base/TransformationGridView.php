<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\grid\base;

use davidhirtz\yii2\media\models\Transformation;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grid\GridView;
use davidhirtz\yii2\skeleton\widgets\fontawesome\Icon;
use davidhirtz\yii2\timeago\TimeagoColumn;
use Yii;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use yii\helpers\Url;

/**
 * @see \davidhirtz\yii2\media\modules\admin\widgets\grid\TransformationGridView
 *
 * @property ActiveDataProvider $dataProvider
 * @method Transformation getModel()
 */
class TransformationGridView extends GridView
{
    use ModuleTrait;

    /**
     * @var File|null the file to display transformations from
     */
    public ?File $file = null;

    public $layout = '{items}{footer}';

    public function init(): void
    {
        if (!$this->dataProvider) {
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
            'headerOptions' => ['style' => 'width:150px'],
            'content' => function (Transformation $transformation) {
                return Html::a('', Url::to($transformation->getFileUrl(), true), [
                    'style' => 'background-image:url(' . ($transformation->getFileUrl()) . ');',
                    'class' => 'thumb',
                    'target' => '_blank',
                ]);
            }
        ];
    }

    public function nameColumn(): array
    {
        return [
            'attribute' => 'name',
            'content' => function (Transformation $transformation) {
                return Html::tag('strong', $transformation->name . ($transformation->isWebp() ? ' (webp)' : ''));
            }
        ];
    }

    public function dimensionsColumn(): array
    {
        return [
            'attribute' => 'dimensions',
            'visible' => $this->file->hasDimensions(),
            'content' => function (Transformation $transformation) {
                return $transformation->width && $transformation->height ? ($transformation->width . ' x ' . $transformation->height) : '';
            }
        ];
    }

    public function sizeColumn(): array
    {
        return [
            'attribute' => 'size',
            'content' => function (Transformation $transformation) {
                return Yii::$app->getFormatter()->asShortSize($transformation->size);
            }
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
            'contentOptions' => ['class' => 'text-right'],
            'content' => function (Transformation $transformation) {
                return Html::buttons(Html::a(Icon::tag('trash'), ['transformation/delete', 'id' => $transformation->id], [
                    'class' => 'btn btn-danger',
                    'data-method' => 'post',
                ]));
            }
        ];
    }

    public function isSortedByPosition(): bool
    {
        return false;
    }
}