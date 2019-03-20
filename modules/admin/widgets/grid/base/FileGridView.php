<?php
namespace davidhirtz\yii2\media\modules\admin\widgets\grid\base;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\media\modules\admin\models\forms\FileForm;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grid\GridView;
use davidhirtz\yii2\timeago\Timeago;
use rmrevin\yii\fontawesome\FAS;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\Url;

/**
 * Class FileGridView.
 * @package davidhirtz\yii2\media\modules\admin\widgets\grid\base
 *
 * @property ActiveDataProvider $dataProvider
 * @method FileForm getModel()
 */
class FileGridView extends GridView
{
    use ModuleTrait;

    /**
     * @var FileForm
     */
    public $file;

    /**
     * @var bool
     */
    public $showUrl = true;

    /**
     * @var string
     */
    public $dateFormat;

    /**
     * @var array
     */
    public $columns = [
        'status',
        'type',
        'name',
        'section_count',
        'publish_date',
        'buttons',
    ];
    
	/**
     * @inheritdoc
	 */
	public function init()
	{
		if($this->file)
		{
			$this->orderRoute=['order', 'id'=>$this->file->id];
		}

		$this->initHeader();
		$this->initFooter();

		parent::init();
	}

	/**
	 * Sets up grid header.
	 */
	protected function initHeader()
	{
		if($this->header===null)
		{
			$this->header=[
				[
					[
						'content'=>$this->renderSearchInput(),
						'options'=>['class'=>'col-12 col-md-6'],
					],
					'options'=>[
						'class'=>FileForm::getTypes() ? 'justify-content-between' : 'justify-content-end',
					],
				],
			];
		}
	}

	/**
     * Sets up grid footer.
	 */
	protected function initFooter()
	{
		if($this->footer===null)
		{
			$this->footer=[
				[
					[
						'content'=>$this->renderCreateFileButton(),
						'visible'=>Yii::$app->getUser()->can('media'),
						'options'=>['class'=>'col'],
					],
				],
			];
		}
	}

	/**
	 * @return string
	 */
	protected function renderCreateFileButton()
	{
		return Html::a(Html::iconText('plus', Yii::t('media', 'New File')), ['create', 'id'=>$this->file ? $this->file->id : null, 'type'=>Yii::$app->getRequest()->get('type')], ['class'=>'btn btn-primary']);
	}

    /**
     * @return array
     */
    public function renderStatusColumn()
    {
        return [
            'contentOptions' => ['class' => 'text-center'],
            'content' => function (FileForm $file) {
                return FAS::icon($file->getStatusIcon(), [
                    'data-toggle' => 'tooltip',
                    'title' => $file->getStatusName()
                ]);
            }
        ];
    }

	/**
	 * @return array
	 */
	public function renderTypeColumn()
	{
		return [
			'attribute'=>'type',
			'visible'=>count(FileForm::getTypes())>1,
			'content'=>function(FileForm $file)
			{
				return Html::a($file->getTypeName(), ['update', 'id'=>$file->id]);
			}
		];
	}

	/**
	 * @return array
	 */
	public function renderNameColumn()
	{
		return [
			'attribute'=>$this->getModel()->getI18nAttributeName('name'),
			'content'=>function(FileForm $file)
			{
				$html=Html::markKeywords(Html::encode($file->getI18nAttribute('name')), $this->search);
				$html=Html::tag('strong', Html::a($html, ['update', 'id'=>$file->id]));

				if($this->showUrl)
				{
					$url=Url::to($file->getRoute(), true);
					$html.=Html::tag('div', Html::a($url, $url, ['target'=>'_blank']), ['class'=>'small hidden-xs']);
				}


				return $html;
			}
		];
	}

	/**
	 * @return array
	 */
	public function renderSectionCountColumn()
	{
		return [
			'attribute'=>'section_count',
			'headerOptions'=>['class'=>'hidden-sm hidden-xs text-center'],
			'contentOptions'=>['class'=>'hidden-sm hidden-xs text-center'],
			'visible'=>static::getModule()->enableSections,
			'content'=>function(FileForm $file)
			{
				return Html::a(Yii::$app->getFormatter()->asInteger($file->section_count), ['section/index', 'file'=>$file->id], ['class'=>'badge']);
			}
		];
	}

	/**
	 * @return array
	 */
	public function renderPublishDateColumn()
	{
		return [
			'attribute'=>'publish_date',
			'headerOptions'=>['class'=>'hidden-sm hidden-xs'],
			'contentOptions'=>['class'=>'text-nowrap hidden-sm hidden-xs'],
			'content'=>function(FileForm $file)
			{
				return $this->dateFormat ? $file->publish_date->format($this->dateFormat) : Timeago::tag($file->publish_date);
			}
		];
	}

	/**
	 * @return array
	 */
	public function renderButtonsColumn()
	{
		return [
			'contentOptions'=>['class'=>'text-right text-nowrap'],
			'content'=>function(FileForm $file)
			{
				$buttons=[];

				if($this->getIsSortedByPosition())
				{
					$buttons[]=Html::tag('span', FAS::icon('arrows-alt'), ['class'=>'btn btn-secondary sortable-handle']);
				}

				$buttons[]=Html::a(FAS::icon('wrench'), ['update', 'id'=>$file->id], ['class'=>'btn btn-secondary']);
				return Html::buttons($buttons);
			}
		];
	}
}