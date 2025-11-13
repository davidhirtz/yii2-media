<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\grids;

use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\modules\admin\controllers\FolderController;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\html\Button;
use davidhirtz\yii2\skeleton\widgets\grids\buttons\DraggableSortButton;
use davidhirtz\yii2\skeleton\widgets\grids\buttons\ViewButton;
use davidhirtz\yii2\skeleton\widgets\grids\columns\ButtonsColumn;
use davidhirtz\yii2\skeleton\widgets\grids\columns\CounterColumn;
use davidhirtz\yii2\skeleton\widgets\grids\GridView;
use davidhirtz\yii2\timeago\TimeagoColumn;
use Override;
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

    #[Override]
    public function init(): void
    {
        $this->columns ??= [
            $this->nameColumn(),
            $this->fileCountColumn(),
            $this->updatedAtColumn(),
            $this->buttonsColumn(),
        ];

        /** @see FolderController::actionOrder() */
        $this->orderRoute = ['order', 'id' => $this->folder?->id];

        parent::init();
    }

    protected function initHeader(): void
    {
        $this->header ??= [
            [
                $this->search->getToolbarItem(),
            ],
        ];

        parent::initHeader();
    }


    protected function initFooter(): void
    {
        if (Yii::$app->getUser()->can(Folder::AUTH_FOLDER_CREATE)) {
            $this->footer ??= [
                [
                    $this->getCreateFolderButton(),
                ],
            ];
        }
    }

    protected function getCreateFolderButton(): string
    {
        return Button::make()
            ->primary()
            ->text(Yii::t('media', 'New Folder'))
            ->icon('plus')
            ->href(['/admin/folder/create'])
            ->render();
    }

    public function nameColumn(): array
    {
        return [
            'attribute' => 'name',
            'content' => fn (Folder $folder) => Html::a($folder->name, $this->getRoute($folder), [
                'class' => 'strong',
            ])
        ];
    }

    public function fileCountColumn(): array
    {
        return [
            'class' => CounterColumn::class,
            'attribute' => 'file_count',
            'route' => fn (Folder $folder) => ['file/index', 'folder' => $folder->id],
        ];
    }

    public function updatedAtColumn(): array
    {
        return [
            'class' => TimeagoColumn::class,
            'attribute' => 'updated_at',
        ];
    }

    public function buttonsColumn(): array
    {
        return [
            'class' => ButtonsColumn::class,
            'content' => function (Folder $folder): array {
                $buttons = [];

                if ($this->isSortedByPosition()) {
                    $buttons[] = new DraggableSortButton();
                }

                $buttons[] = new ViewButton($folder);

                return $buttons;
            }
        ];
    }

    public function isSortedByPosition(): bool
    {
        return $this->dataProvider->getCount() > 1
            && $this->dataProvider->query instanceof Query
            && key($this->dataProvider->query->orderBy) === 'position';
    }

    #[Override]
    public function getModel(): ?Folder
    {
        return Folder::instance();
    }
}
