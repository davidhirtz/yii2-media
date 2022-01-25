<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\grid\base;

use davidhirtz\yii2\media\assets\AdminAsset;
use davidhirtz\yii2\media\models\AssetParentInterface;
use davidhirtz\yii2\media\modules\admin\data\FileActiveDataProvider;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\admin\widgets\UploadTrait;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\modules\admin\widgets\FolderDropdownTrait;
use davidhirtz\yii2\skeleton\helpers\ArrayHelper;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grid\GridView;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ButtonDropdown;
use davidhirtz\yii2\timeago\Timeago;
use davidhirtz\yii2\skeleton\widgets\fontawesome\Icon;
use Yii;
use yii\helpers\Url;

/**
 * Class FileGridView
 * @package davidhirtz\yii2\media\modules\admin\widgets\grid\base
 * @see \davidhirtz\yii2\media\modules\admin\widgets\grid\FileGridView
 *
 * @property FileActiveDataProvider $dataProvider
 * @method File getModel()
 */
class FileGridView extends GridView
{
    use FolderDropdownTrait;
    use ModuleTrait;
    use UploadTrait;

    /**
     * @var Folder
     */
    public $folder;

    /**
     * @var AssetParentInterface the parent record linked via Asset
     */
    public $parent;

    /**
     * @var array
     */
    public $columns = [
        'thumbnail',
        'name',
        'filename',
        'assetCount',
        'alt_text',
        'updated_at',
        'buttons',
    ];

    /**
     * @inheritDoc
     */
    public function init()
    {
        if (!$this->folder) {
            $this->folder = $this->dataProvider->folder;
        }

        if ($this->parent) {
            $fileIds = ArrayHelper::getColumn($this->parent->assets, 'file_id');
            $this->rowOptions = function (File $file) use ($fileIds) {
                return [
                    'id' => $this->getRowId($file),
                    'class' => in_array($file->id, $fileIds) ? 'is-selected' : null,
                ];
            };
        }

        if (Yii::$app->getUser()->can('fileCreate', ['folder' => $this->folder])) {
            AdminAsset::register($view = $this->getView());
            $view->registerJs('Skeleton.mediaFileImport();');
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
                        'content' => $this->folderDropdown(),
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
                        'content' => Html::buttons($this->getFooterButtons()),
                        'options' => ['class' => 'col'],
                    ],
                ],
            ];
        }
    }

    /**
     * @return array
     */
    protected function getFooterButtons(): array
    {
        if (!Yii::$app->getUser()->can('fileCreate', ['folder' => $this->folder])) {
            return [];
        }

        return [$this->getUploadFileButton(), $this->getImportFileButton()];
    }

    /**
     * @return string
     */
    public function renderItems(): string
    {
        return Html::tag('div', parent::renderItems(), ['id' => 'files']);
    }

    /**
     * @return array
     */
    public function thumbnailColumn(): array
    {
        return [
            'headerOptions' => ['style' => 'width:150px'],
            'content' => function (File $file) {
                return !$file->hasPreview() ? '' : Html::a('', ['/admin/file/update', 'id' => $file->id], [
                    'style' => 'background-image:url(' . ($file->getTransformationUrl('admin') ?: $file->getUrl()) . ');',
                    'class' => 'thumb',
                ]);
            }
        ];
    }

    /**
     * @return array
     */
    public function nameColumn(): array
    {
        return [
            'attribute' => 'name',
            'content' => function (File $file) {
                $html = Html::tag('strong', Html::a(Html::encode($file->name), ['/admin/file/update', 'id' => $file->id]));

                if (!$this->folder) {
                    $html .= Html::tag('div', Html::a(Html::encode($file->folder->name), Url::current(['folder' => $file->folder_id, 'page' => 0])), ['class' => 'd-none d-md-block small']);
                }

                return $html;
            }
        ];
    }

    /**
     * @return array
     */
    public function filenameColumn(): array
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
    public function assetCountColumn(): array
    {
        return [
            'attribute' => Yii::t('media', 'Assets'),
            'headerOptions' => ['class' => 'd-none d-md-table-cell text-center'],
            'contentOptions' => ['class' => 'd-none d-md-table-cell text-center'],
            'content' => function (File $file) {
                return $file->getAssetCount() ? Html::a(Yii::$app->getFormatter()->asInteger($file->getAssetCount()), ['/admin/file/update', 'id' => $file->id, '#' => 'assets'], ['class' => 'badge']) : '';
            }
        ];
    }

    /**
     * @return array
     */
    public function altTextColumn(): array
    {
        return [
            'attribute' => $this->getModel()->getI18nAttributeName('alt_text'),
            'headerOptions' => ['class' => 'd-none d-md-table-cell text-center'],
            'contentOptions' => ['class' => 'd-none d-md-table-cell text-center'],
            'content' => function (File $file) {
                return $file->getI18nAttribute('alt_text') ? Html::a(Icon::tag('check'), ['/admin/file/update', 'id' => $file->id, '#' => 'assets'], ['class' => 'text-success']) : '';
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
            'headerOptions' => ['class' => 'd-none d-md-table-cell text-nowrap'],
            'contentOptions' => ['class' => 'd-none d-md-table-cell text-nowrap'],
            'content' => function (File $file) {
                return Timeago::tag($file->updated_at);
            }
        ];
    }

    /**
     * @return array
     */
    public function buttonsColumn(): array
    {
        return [
            'contentOptions' => ['class' => 'text-right text-nowrap'],
            'content' => function (File $file) {
                $buttons = [
                    Html::a(Icon::tag($this->parent ? 'image' : 'wrench'), ['/admin/file/update', 'id' => $file->id], [
                        'class' => 'btn btn-' . ($this->parent ? 'secondary' : 'primary') . ' d-none d-md-inline-block',
                    ])
                ];

                if ($this->parent) {
                    $buttons[] = Html::a(Icon::tag('plus'), ['create', strtolower($this->parent->formName()) => $this->parent->getPrimaryKey(), 'file' => $file->id], [
                        'class' => 'btn btn-primary',
                        'data-ajax' => 'select',
                        'data-target' => '#' . $this->getRowId($file),
                    ]);
                } else {
                    $buttons[] = Html::a(Icon::tag('trash'), ['delete', 'id' => $file->id], [
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
     * @return array
     */
    protected function getCreateRoute(): array
    {
        return ['create', 'folder' => $this->folder ? $this->folder->id : null, $this->parent ? strtolower($this->parent->formName()) : '#' => $this->parent ? $this->parent->getPrimaryKey() : null];
    }

    /**
     * @return string
     */
    public function folderDropdown(): string
    {
        return !$this->getFolders() ? '' : ButtonDropdown::widget([
            'label' => $this->folder ? $this->folder->name : Yii::t('media', 'Folders'),
            'items' => $this->folderDropdownItems(),
            'paramName' => 'folder',
        ]);
    }

    /**
     * @return array
     */
    protected function folderDropdownItems(): array
    {
        $items = [];

        foreach ($this->getFolders() as $id => $name) {
            $items[] = [
                'label' => $name,
                'url' => Url::current(['folder' => $id, 'page' => null]),
            ];
        }

        return $items;
    }
}