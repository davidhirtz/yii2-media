<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\grid\base;

use davidhirtz\yii2\media\models\Transformation;
use davidhirtz\yii2\media\modules\admin\models\forms\FileForm;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grid\GridView;
use davidhirtz\yii2\timeago\Timeago;
use rmrevin\yii\fontawesome\FAS;
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
     * @var FileForm
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
                'allModels' => $this->file->transformations,
                'pagination' => false,
                'sort' => false,
            ]);

            $this->setModel(new Transformation);
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
                return Html::tag('strong', $transformation->name);
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
                return $transformation->width . ' x ' .  $transformation->height;
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
            'headerOptions' => ['class' => 'hidden-sm hidden-xs'],
            'contentOptions' => ['class' => 'text-nowrap hidden-sm hidden-xs'],
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
                return Html::buttons(Html::a(FAS::icon('trash'), ['media/transformation/delete', 'id' => $transformation->id], [
                    'class' => 'btn btn-danger',
                    'data-method' => 'post',
                ]));
            }
        ];
    }
}