<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\grid\base;

use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grid\GridView;
use davidhirtz\yii2\timeago\Timeago;
use davidhirtz\yii2\skeleton\widgets\fontawesome\Icon;
use Yii;
use yii\data\ActiveDataProvider;

/**
 * Class FolderGridView.
 * @package davidhirtz\yii2\media\modules\admin\widgets\grid\base
 *
 * @property ActiveDataProvider $dataProvider
 * @method Folder getModel()
 */
class FolderGridView extends GridView
{
    use ModuleTrait;

    /**
     * @var Folder
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
                        'class' => Folder::getTypes() ? 'justify-content-between' : 'justify-content-end',
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
                        'content' => $this->getCreateFolderButton(),
                        'visible' => Yii::$app->getUser()->can('folderCreate'),
                        'options' => ['class' => 'col'],
                    ],
                ],
            ];
        }
    }

    /**
     * @return string
     */
    protected function getCreateFolderButton(): string
    {
        return Html::a(Html::iconText('plus', Yii::t('media', 'New Folder')), ['/admin/folder/create'], ['class' => 'btn btn-primary']);
    }

    /**
     * @return array
     */
    public function typeColumn(): array
    {
        return [
            'attribute' => 'type',
            'headerOptions' => ['class' => 'd-none d-md-table-cell'],
            'contentOptions' => ['class' => 'd-none d-md-table-cell'],
            'visible' => Folder::getTypes(),
            'content' => function (Folder $folder) {
                return Html::a($folder->getTypeName(), ['update', 'id' => $folder->id]);
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
            'content' => function (Folder $folder) {
                return Html::a($folder->name, ['update', 'id' => $folder->id], ['class' => 'strong']);
            }
        ];
    }

    /**
     * @return array
     */
    public function fileCountColumn(): array
    {
        return [
            'attribute' => 'file_count',
            'headerOptions' => ['class' => 'd-none d-md-table-cell text-center'],
            'contentOptions' => ['class' => 'd-none d-md-table-cell text-center'],
            'content' => function (Folder $folder) {
                return Html::a(Yii::$app->getFormatter()->asInteger($folder->file_count), ['file/index', 'folder' => $folder->id], ['class' => 'badge']);
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
            'content' => function (Folder $folder) {
                return Timeago::tag($folder->updated_at);
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
            'content' => function (Folder $folder) {
                $buttons = [];

                if ($this->isSortedByPosition()) {
                    $buttons[] = Html::tag('span', Icon::tag('arrows-alt'), ['class' => 'btn btn-secondary sortable-handle']);
                }

                $buttons[] = Html::a(Icon::tag('wrench'), ['update', 'id' => $folder->id], ['class' => 'btn btn-primary d-none d-md-inline-block']);
                return Html::buttons($buttons);
            }
        ];
    }

    /**
     * @return bool
     */
    public function isSortedByPosition(): bool
    {
        return $this->dataProvider->getCount() > 1 && key($this->dataProvider->query->orderBy) === 'position';
    }
}