<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\grid\base;

use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\media\modules\admin\models\forms\FolderForm;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grid\GridView;
use rmrevin\yii\fontawesome\FAS;
use Yii;
use yii\data\ActiveDataProvider;

/**
 * Class FolderGridView.
 * @package davidhirtz\yii2\media\modules\admin\widgets\grid\base
 *
 * @property ActiveDataProvider $dataProvider
 * @method FolderForm getModel()
 */
class FolderGridView extends GridView
{
    use ModuleTrait;

    /**
     * @var FolderForm
     */
    public $folder;

    /**
     * @var array
     */
    public $columns = [
        'name',
        'file_count',
        'updated_at',
        'buttons',
    ];

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->orderRoute = ['order', 'id' => $this->folder ? $this->folder->id : null];

        $this->initHeader();
        $this->initFooter();

        parent::init();
    }

    /**
     * Sets up grid header.
     */
    protected function initHeader()
    {
        if ($this->header === null) {
            $this->header = [
                [
                    [
                        'content' => $this->getSearchInput(),
                        'options' => ['class' => 'col-12 col-md-6'],
                    ],
                    'options' => [
                        'class' => FolderForm::getTypes() ? 'justify-content-between' : 'justify-content-end',
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
        if ($this->footer === null) {
            $this->footer = [
                [
                    [
                        'content' => $this->renderCreateFolderButton(),
                        'visible' => Yii::$app->getUser()->can('upload'),
                        'options' => ['class' => 'col'],
                    ],
                ],
            ];
        }
    }

    /**
     * @return string
     */
    protected function renderCreateFolderButton()
    {
        return Html::a(Html::iconText('plus', Yii::t('media', 'New Folder')), ['create', 'id' => $this->folder ? $this->folder->id : null], ['class' => 'btn btn-primary']);
    }

    /**
     * @return array
     */
    public function typeColumn()
    {
        return [
            'attribute' => 'type',
            'visible' => FolderForm::getTypes(),
            'content' => function (FolderForm $folder) {
                return Html::a($folder->getTypeName(), ['update', 'id' => $folder->id]);
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
            'content' => function (FolderForm $folder) {
                return Html::a($folder->name, ['update', 'id' => $folder->id], ['class' => 'strong']);
            }
        ];
    }

    /**
     * @return array
     */
    public function fileCountColumn()
    {
        return [
            'attribute' => 'file_count',
            'headerOptions' => ['class' => 'hidden-sm hidden-xs text-center'],
            'contentOptions' => ['class' => 'hidden-sm hidden-xs text-center'],
            'content' => function (FolderForm $folder) {
                return Html::a(Yii::$app->getFormatter()->asInteger($folder->file_count), ['file/index', 'folder' => $folder->id], ['class' => 'badge']);
            }
        ];
    }

    /**
     * @return array
     */
    public function buttonsColumn()
    {
        return [
            'contentOptions' => ['class' => 'text-right text-nowrap'],
            'content' => function (FolderForm $folder) {
                $buttons = [];

                if ($this->dataProvider->getCount() > 1) {
                    $buttons[] = Html::tag('span', FAS::icon('arrows-alt'), ['class' => 'btn btn-secondary sortable-handle']);
                }

                $buttons[] = Html::a(FAS::icon('wrench'), ['update', 'id' => $folder->id], ['class' => 'btn btn-secondary']);
                return Html::buttons($buttons);
            }
        ];
    }
}