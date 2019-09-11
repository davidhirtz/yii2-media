<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\grid\base;

use davidhirtz\yii2\media\modules\admin\data\FileActiveDataProvider;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\admin\widgets\forms\FileUpload;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\widgets\FolderDropdownTrait;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grid\GridView;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ButtonDropdown;
use davidhirtz\yii2\timeago\Timeago;
use rmrevin\yii\fontawesome\FAS;
use Yii;
use yii\helpers\Url;

/**
 * Class FileGridView.
 * @package davidhirtz\yii2\media\modules\admin\widgets\grid\base
 *
 * @property FileActiveDataProvider $dataProvider
 * @method File getModel()
 */
class FileGridView extends GridView
{
    use FolderDropdownTrait, ModuleTrait;

    /**
     * @var Folder
     */
    public $folder;

    /**
     * @var ActiveRecord
     */
    public $parent;

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
        if (!$this->folder) {
            $this->folder = $this->dataProvider->folder;
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
        if ($this->header === null) {
            $this->header = [
                [
                    [
                        'content' => $this->getFolderDropDown(),
                        'options' => ['class' => 'col-12 col-md-3'],
                    ],
                    [
                        'content' => $this->getSearchInput(),
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

    /**
     * @return string
     */
    protected function getFileUploadWidget()
    {
        return FileUpload::widget([
            'url' => ['create', 'folder' => $this->folder ? $this->folder->id : null, $this->parent ? strtolower($this->parent->formName()) : '#' => $this->parent ? $this->parent->getPrimaryKey() : null],
        ]);
    }

    /**
     * @return string|null
     */
    public function renderItems()
    {
        return Html::tag('div', parent::renderItems(), ['id' => 'files']);
    }

    /**
     * @return array
     */
    public function thumbnailColumn()
    {
        return [
            'headerOptions' => ['style' => 'width:150px'],
            'content' => function (File $file) {
                return !$file->hasPreview() ? '' : Html::a('', ['update', 'id' => $file->id], [
                    'style' => 'background-image:url(' . ($file->getTransformationUrl('admin') ?: $file->getUrl()) . ');',
                    'class' => 'thumb',
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
            'content' => function (File $file) {
                $html = Html::tag('strong', Html::a($file->name, ['/admin/file/update', 'id' => $file->id]));

                if (!$this->folder) {
                    $html .= Html::tag('div', Html::a($file->folder->name, Url::current(['folder' => $file->folder_id, 'page' => 0])), ['class' => 'd-none d-md-block small']);
                }

                return $html;
            }
        ];
    }

    /**
     * @return array
     */
    public function filenameColumn()
    {
        return [
            'attribute' => 'filename',
            'headerOptions' => ['class' => 'd-none d-md-table-cell'],
            'contentOptions' => ['class' => 'd-none d-md-table-cell'],
            'content' => function (File $file) {
                return $file->getFilename();
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
            'headerOptions' => ['class' => 'd-none d-md-table-cell'],
            'contentOptions' => ['class' => 'd-none d-md-table-cell text-nowrap'],
            'content' => function (File $file) {
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
            'content' => function (File $file) {

                $buttons = [Html::a(FAS::icon($this->parent ? 'image' : 'wrench'), ['file/update', 'id' => $file->id], ['class' => 'btn btn-secondary d-none d-md-inline-block'])];

                if ($this->parent) {
                    $buttons[] = Html::a(FAS::icon('plus'), ['create', strtolower($this->parent->formName()) => $this->parent->getPrimaryKey(), 'file' => $file->id], [
                        'class' => 'btn btn-primary',
                        'data-method' => 'post',
                    ]);

                } else {
                    $buttons[] = Html::a(FAS::icon('trash'), ['delete', 'id' => $file->id], [
                        'class' => 'btn btn-danger d-none d-md-inline-block',
                        'data-confirm' => Yii::t('yii', 'Are you sure you want to delete this item?'),
                        'data-ajax' => 'remove',
                        'data-target' => '#' . $this->getRowId($file),
                    ]);
                }

                return Html::buttons($buttons);
            }
        ];
    }

    /**
     * @return string
     */
    public function getFolderDropDown()
    {
        if ($folders = $this->getFolders()) {
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

        return null;
    }
}