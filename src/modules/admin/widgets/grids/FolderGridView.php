<?php

namespace davidhirtz\yii2\media\modules\admin\widgets\grids;

use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grids\GridView;
use davidhirtz\yii2\skeleton\widgets\fontawesome\Icon;
use davidhirtz\yii2\timeago\TimeagoColumn;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecordInterface;

/**
 * @property ActiveDataProvider $dataProvider
 */
class FolderGridView extends GridView
{
    use ModuleTrait;

    public ?Folder $folder = null;

    public function init(): void
    {
        if (!$this->columns) {
            $this->columns = [
                $this->nameColumn(),
                $this->fileCountColumn(),
                $this->updatedAtColumn(),
                $this->buttonsColumn(),
            ];
        }

        $this->orderRoute = ['order', 'id' => $this->folder?->id];

        $this->initHeader();
        $this->initFooter();

        parent::init();
    }

    protected function initHeader(): void
    {
        $this->header ??= [
            [
                [
                    'content' => $this->getSearchInput(),
                    'options' => ['class' => 'col-12 col-md-6'],
                ],
                'options' => [
                    'class' => $this->getModel()::getTypes() ? 'justify-content-between' : 'justify-content-end',
                ],
            ],
        ];
    }

    protected function initFooter(): void
    {
        $this->footer ??= [
            [
                [
                    'content' => $this->getCreateFolderButton(),
                    'visible' => Yii::$app->getUser()->can('folderCreate'),
                    'options' => ['class' => 'col'],
                ],
            ],
        ];
    }

    protected function getCreateFolderButton(): string
    {
        return Html::a(Html::iconText('plus', Yii::t('media', 'New Folder')), ['/admin/folder/create'], ['class' => 'btn btn-primary']);
    }

    public function typeColumn(): array
    {
        return [
            'attribute' => 'type',
            'headerOptions' => ['class' => 'd-none d-md-table-cell'],
            'contentOptions' => ['class' => 'd-none d-md-table-cell'],
            'visible' => $this->getModel()::getTypes() > 1,
            'content' => fn(Folder $folder) => Html::a($folder->getTypeName(), ['update', 'id' => $folder->id])
        ];
    }

    public function nameColumn(): array
    {
        return [
            'attribute' => 'name',
            'content' => fn(Folder $folder) => Html::a($folder->name, ['update', 'id' => $folder->id], ['class' => 'strong'])
        ];
    }

    public function fileCountColumn(): array
    {
        return [
            'attribute' => 'file_count',
            'headerOptions' => ['class' => 'd-none d-md-table-cell text-center'],
            'contentOptions' => ['class' => 'd-none d-md-table-cell text-center'],
            'content' => fn(Folder $folder) => Html::a(Yii::$app->getFormatter()->asInteger($folder->file_count), ['file/index', 'folder' => $folder->id], ['class' => 'badge'])
        ];
    }

    public function updatedAtColumn(): array
    {
        return [
            'attribute' => 'updated_at',
            'class' => TimeagoColumn::class,
        ];
    }

    public function buttonsColumn(): array
    {
        return [
            'contentOptions' => ['class' => 'text-right text-nowrap'],
            'content' => function (Folder $folder): string {
                $buttons = [];

                if ($this->isSortedByPosition()) {
                    $buttons[] = Html::tag('span', Icon::tag('arrows-alt'), ['class' => 'btn btn-secondary sortable-handle']);
                }

                $buttons[] = Html::a(Icon::tag('wrench'), ['update', 'id' => $folder->id], ['class' => 'btn btn-primary d-none d-md-inline-block']);
                return Html::buttons($buttons);
            }
        ];
    }

    public function isSortedByPosition(): bool
    {
        return $this->dataProvider->getCount() > 1 && key($this->dataProvider->query->orderBy) === 'position';
    }

    /**
     * @return Folder|null
     */
    public function getModel(): ?ActiveRecordInterface
    {
        return Folder::instance();
    }
}