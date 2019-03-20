<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\grid\base;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\admin\models\forms\FolderForm;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\media\modules\admin\models\forms\FileForm;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grid\GridView;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ButtonDropdown;
use davidhirtz\yii2\skeleton\widgets\forms\FileUpload;
use davidhirtz\yii2\timeago\Timeago;
use rmrevin\yii\fontawesome\FAS;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\Url;
use yii\web\JsExpression;

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
     * @var FolderForm
     */
    public $folder;

    /**
     * @var array
     */
    private $_folders;

    /**
     * @var array
     */
    public $columns = [
        'thumbnail',
        'name',
        'filename',
        'updated_at',
        'buttons',
    ];

    /**
     * @inheritdoc
     */
    public function init()
    {
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
                        'content' => $this->getFolderDropDown(),
                        'options' => ['class' => 'col-12 col-md-3'],
                    ],
                    [
                        'content' => $this->renderSearchInput(),
                        'options' => ['class' => 'col-12 col-md-6'],
                    ],
                    'options' => [
                        'class' => $this->getFolders() ? 'justify-content-between' : 'justify-content-end',
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
                        'content' => $this->getCreateFileButton(),
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
    protected function getCreateFileButton()
    {
        return Html::tag('div', Html::iconText('plus', Yii::t('media', 'Upload Files') . $this->getFileUploadWidget()), ['class' => 'btn btn-primary btn-upload']);
    }


    protected function getFileUploadWidget()
    {
        return FileUpload::widget([
            'model' => new FileForm(),
            'url' => ['create', 'folder' => $this->folder ? $this->folder->id : null],
            'clientEvents' => [
                'fileuploaddone' => new JsExpression('function(){$.get(document.location.href, function(d){$(\'#files\').replaceWith($(\'<div>\').html(d).find(\'#files\').html());$(\'.timeago\').timeago();})}'),
            ],
        ]);
    }

    /**
     * @return string|null
     */
    public function renderItems()
    {
        return Html::tag('div', parent::renderItems(), ['id' => 'files']);
    }

    public function thumbnailColumn()
    {
        return [
            'attribute' => 'name',
            'content' => function (FileForm $file) {
                return !$file->hasThumbnail() ? '' : Html::tag('div', '', [
                    'style' => 'width:100px; height:80px; background:url(' . $file->folder->getUploadUrl() . $file->filename . ') no-repeat center; background-size:contain;',
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
            'content' => function (FileForm $file) {
                $html = Html::tag('strong', Html::a($file->name, ['update', 'id' => $file->id]));

                if (!$this->folder) {
                    $html .= Html::tag('div', Html::a($file->folder->name, Url::current(['folder' => $file->folder_id, 'page' => 0])), ['class' => 'small hidden-xs']);
                }

                return $html;
            }
        ];
    }

    /**
     * @return array
     */
    public function updatedAtColumn()
    {
        return [
            'attribute' => 'updated_at',
            'headerOptions' => ['class' => 'hidden-sm hidden-xs'],
            'contentOptions' => ['class' => 'text-nowrap hidden-sm hidden-xs'],
            'content' => function (FileForm $file) {
                return Timeago::tag($file->updated_at);
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
            'content' => function (FileForm $file) {

                return Html::buttons([
                    Html::a(FAS::icon('wrench'), ['update', 'id' => $file->id], ['class' => 'btn btn-secondary']),
                    Html::a(FAS::icon('trash'), ['delete', 'id' => $file->id], [
                        'class' => 'btn btn-danger',
                        'data-confirm' => Yii::t('yii', 'Are you sure you want to delete this item?'),
                        'data-method' => 'post',
                    ]),
                ]);
            }
        ];
    }

    /**
     * @return string
     */
    public function getFolderDropDown()
    {
        $config = [
            'label' => $this->folder ? $this->folder->name : Yii::t('media', 'Folders'),
            'paramName' => 'folder',
        ];


        foreach ($this->getFolders() as $id => $name) {
            $config['items'][] = [
                'label' => $name,
                'url' => Url::current(['folder' => $id, 'page' => null]),
            ];
        }

        return ButtonDropdown::widget($config);
    }

    /**
     * @return array
     */
    public function getFolders()
    {
        if ($this->_folders === null) {
            $this->_folders = Folder::find()
                ->select(['name'])
                ->orderBy(['position' => SORT_ASC])
                ->indexBy('id')
                ->column();
        }

        return $this->_folders;
    }
}