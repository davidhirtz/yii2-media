<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\grids;

use davidhirtz\yii2\media\models\collections\FolderCollection;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\models\interfaces\AssetParentInterface;
use davidhirtz\yii2\media\modules\admin\data\FileActiveDataProvider;
use davidhirtz\yii2\media\modules\admin\widgets\grids\columns\FileThumbnailColumn;
use davidhirtz\yii2\media\modules\admin\widgets\grids\traits\FileGridViewTrait;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\helpers\ArrayHelper;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\html\A;
use davidhirtz\yii2\skeleton\html\Button;
use davidhirtz\yii2\skeleton\widgets\grids\buttons\DeleteButton;
use davidhirtz\yii2\skeleton\widgets\grids\columns\ButtonsColumn;
use davidhirtz\yii2\skeleton\widgets\grids\columns\CounterColumn;
use davidhirtz\yii2\skeleton\widgets\grids\FilterDropdown;
use davidhirtz\yii2\skeleton\widgets\grids\GridView;
use davidhirtz\yii2\timeago\TimeagoColumn;
use Override;
use Yii;
use yii\db\ActiveRecordInterface;
use yii\helpers\Url;

/**
 * @extends GridView<File>
 * @property FileActiveDataProvider $dataProvider
 */
class FileGridView extends GridView
{
    use FileGridViewTrait;
    use ModuleTrait;

    public ?Folder $folder = null;
    public ?AssetParentInterface $parent = null;

    #[Override]
    public function init(): void
    {
        $this->id = $this->getId(false) ?? 'files';
        $this->folder ??= $this->dataProvider->folder;

        if ($this->parent) {
            $fileIds = array_map(intval(...), array_column($this->parent->assets, 'file_id'));

            $this->rowAttributes = fn (File $file) => [
                'class' => in_array($file->id, $fileIds, true) ? 'is-selected' : null,
            ];
        }

        $this->columns ??= [
            $this->thumbnailColumn(),
            $this->nameColumn(),
            $this->filenameColumn(),
            $this->assetCountColumn(),
            $this->altTextColumn(),
            $this->updatedAtColumn(),
            $this->buttonsColumn(),
        ];

        parent::init();
    }

    protected function initHeader(): void
    {
        $this->header ??= [
            [
                $this->getFolderDropdown(),
                $this->search->getToolbarItem(),
            ],
        ];
    }

    protected function getFolderDropdown(): ?FilterDropdown
    {
        $items = $this->getFolderDropdownItems();

        return count($items) > 1
            ? new FilterDropdown(
                $items,
                Yii::t('media', 'Folders'),
                'folder'
            )
            : null;
    }

    protected function getFolderDropdownItems(): array
    {
        return ArrayHelper::getColumn(FolderCollection::getAll(), 'name');
    }

    protected function initFooter(): void
    {
        $this->footer ??= [
            [
                ...$this->getFooterButtons(),
            ],
        ];
    }

    protected function getFooterButtons(): array
    {
        if (!Yii::$app->getUser()->can(File::AUTH_FILE_CREATE, ['folder' => $this->folder])) {
            return [];
        }

        return [
            $this->getUploadFileButton(),
            $this->getImportFileButton(),
        ];
    }

    protected function thumbnailColumn(): array
    {
        return [
            'class' => FileThumbnailColumn::class,
            'route' => fn (File $file) => $this->getRoute($file),
        ];
    }

    protected function nameColumn(): array
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

    protected function filenameColumn(): array
    {
        return [
            'attribute' => 'filename',
            'headerOptions' => ['class' => 'd-none d-md-table-cell'],
            'contentOptions' => ['class' => 'd-none d-md-table-cell'],
            'content' => fn (File $file): string => $file->getFilename()
        ];
    }

    protected function assetCountColumn(): array
    {
        return [
            'label' => Yii::t('media', 'Assets'),
            'class' => CounterColumn::class,
            'value' => fn (File $file) => $file->getRelatedModelCount(),
            'route' => fn (File $file) => ['/admin/file/update', 'id' => $file->id, '#' => 'assets'],
        ];
    }

    protected function altTextColumn(): array
    {
        $options = ['class' => 'd-none d-md-table-cell text-center'];

        return [
            'attribute' => $this->getModel()->getI18nAttributeName('alt_text'),
            'headerOptions' => $options,
            'contentOptions' => $options,
            'content' => function (File $file) {
                if (!$file->getI18nAttribute('alt_text')) {
                    return '';
                }

                return A::make()
                    ->href($this->getRoute($file, ['#' => 'assets']))
                    ->icon('check')
                    ->addClass('text-success');
            }
        ];
    }

    protected function updatedAtColumn(): array
    {
        return [
            'attribute' => 'updated_at',
            'class' => TimeagoColumn::class,
        ];
    }

    protected function buttonsColumn(): array
    {
        return [
            'class' => ButtonsColumn::class,
            'content' => function (File $file): array {
                if ($this->parent) {
                    $route = [
                        'create',
                        strtolower($this->parent->formName()) => $this->parent->getPrimaryKey(),
                        'file' => $file->id,
                    ];

                    return [
                        Button::make()
                            ->secondary()
                            ->icon('image')
                            ->href(['/admin/file/update', 'id' => $file->id])
                            ->addClass('d-none d-md-block'),
                        Button::make()
                            ->primary()
                            ->icon('plus')
                            ->post($route),
                    ];
                }

                return [
                    Button::make()
                        ->primary()
                        ->icon('wrench')
                        ->href(['/admin/file/update', 'id' => $file->id])
                        ->addClass('d-none d-md-block')
                        ->render(),
                    new DeleteButton($file),
                ];
            }
        ];
    }

    protected function getFileUploadRoute(): array
    {
        return [
            'create',
            'folder' => $this->folder?->id,
            ...$this->parent
                ? [strtolower($this->parent->formName()) => $this->parent->getPrimaryKey()]
                : []
        ];
    }

    /**
     * @param File $model
     */
    #[Override]
    protected function getRoute(ActiveRecordInterface $model, array $params = []): array|false
    {
        return ['/admin/file/update', 'id' => $model->id, ...$params];
    }

    public function getModel(): File
    {
        return File::instance();
    }
}
