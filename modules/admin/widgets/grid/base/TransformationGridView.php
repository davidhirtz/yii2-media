<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\grid\base;

use davidhirtz\yii2\media\models\Transformation;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grid\GridView;
use davidhirtz\yii2\timeago\Timeago;
use davidhirtz\yii2\skeleton\widgets\fontawesome\Icon;
use Yii;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use yii\helpers\Url;

/**
 * Class TransformationGridView.
 * @package davidhirtz\yii2\media\modules\admin\widgets\grid\base
 *
 * @property ActiveDataProvider $dataProvider
 * @method Transformation getModel()
 */
class TransformationGridView extends GridView
{
    use ModuleTrait;

    /**
     * @var File
     */
    public $file;

    /**
     * @var array
     */
    public $columns = [
        'thumbnail',
        'name',
        'dimensions',
        'size',
        'created_at',
        'buttons',
    ];

    public $layout = '{items}{footer}';

    /**
     * @inheritdoc
     */
    public function init()
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

        parent::init();
    }

    /**
     * @return array
     */
    public function thumbnailColumn()
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

    /**
     * @return array
     */
    public function nameColumn()
    {
        return [
            'attribute' => 'name',
            'content' => function (Transformation $transformation) {
                return Html::tag('strong', $transformation->name . ($transformation->isWebp() ? ' (webp)' : ''));
            }
        ];
    }

    /**
     * @return array
     */
    public function dimensionsColumn()
    {
        return [
            'attribute' => 'dimensions',
            'content' => function (Transformation $transformation) {
                return $transformation->width . ' x ' . $transformation->height;
            }
        ];
    }

    /**
     * @return array
     */
    public function sizeColumn()
    {
        return [
            'attribute' => 'size',
            'content' => function (Transformation $transformation) {
                return Yii::$app->getFormatter()->asShortSize($transformation->size);
            }
        ];
    }

    /**
     * @return array
     */
    public function createdAtColumn()
    {
        return [
            'attribute' => 'created_at',
            'headerOptions' => ['class' => 'd-none d-md-table-cell'],
            'contentOptions' => ['class' => 'd-none d-md-table-cell text-nowrap'],
            'content' => function (Transformation $transformation) {
                return Timeago::tag($transformation->created_at);
            }
        ];
    }


    /**
     * @return array
     */
    public function buttonsColumn()
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

    /**
     * @return bool
     */
    public function isSortedByPosition(): bool
    {
        return false;
    }
}