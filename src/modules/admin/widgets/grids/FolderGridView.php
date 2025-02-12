<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\grids;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grids\GridView;
use davidhirtz\yii2\skeleton\widgets\fontawesome\Icon;
use davidhirtz\yii2\timeago\TimeagoColumn;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Query;

/**
 * @extends GridView<Folder>
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
                    'class' => count($this->getModel()::getTypes()) > 1
                        ? 'justify-content-between'
                        : 'justify-content-end',
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
                    'visible' => Yii::$app->getUser()->can(Folder::AUTH_FOLDER_CREATE),
                    'options' => ['class' => 'col'],
                ],
            ],
        ];
    }

    protected function getCreateFolderButton(): string
    {
        return Html::a(Html::iconText('plus', Yii::t('media', 'New Folder')), ['/admin/folder/create'], ['class' => 'btn btn-primary']);
    }

    public function nameColumn(): array
    {
        return [
            'attribute' => 'name',
            'content' => fn (Folder $folder) => Html::a($folder->name, ['update', 'id' => $folder->id], ['class' => 'strong'])
        ];
    }

    public function fileCountColumn(): array
    {
        return [
            'attribute' => 'file_count',
            'headerOptions' => ['class' => 'd-none d-md-table-cell text-center'],
            'contentOptions' => ['class' => 'd-none d-md-table-cell text-center'],
            'content' => fn (Folder $folder) => Html::a(Yii::$app->getFormatter()->asInteger($folder->file_count), ['file/index', 'folder' => $folder->id], ['class' => 'badge'])
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
                    $buttons[] = Html::tag('span', (string)Icon::tag('arrows-alt'), [
                        'class' => 'btn btn-secondary sortable-handle',
                    ]);
                }

                $buttons[] = Html::a((string)Icon::tag('wrench'), ['update', 'id' => $folder->id], [
                    'class' => 'btn btn-primary d-none d-md-inline-block',
                ]);

                return Html::buttons($buttons);
            }
        ];
    }

    public function isSortedByPosition(): bool
    {
        return $this->dataProvider->getCount() > 1
            && $this->dataProvider->query instanceof Query
            && key($this->dataProvider->query->orderBy) === 'position';
    }

    public function getModel(): ?Folder
    {
        return Folder::instance();
    }
}
