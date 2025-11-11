<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\grids;

use davidhirtz\yii2\media\models\collections\FolderCollection;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\models\interfaces\AssetParentInterface;
use davidhirtz\yii2\media\modules\admin\data\FileActiveDataProvider;
use davidhirtz\yii2\media\modules\admin\widgets\buttons\ImportFileButton;
use davidhirtz\yii2\media\modules\admin\widgets\buttons\UploadFileButton;
use davidhirtz\yii2\media\modules\admin\widgets\grids\columns\FileThumbnailColumn;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\helpers\ArrayHelper;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\html\Button;
use davidhirtz\yii2\skeleton\html\Icon;
use davidhirtz\yii2\skeleton\html\Link;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grids\buttons\DeleteButton;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grids\columns\ButtonsColumn;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grids\columns\CounterColumn;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grids\FilterDropdown;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grids\GridView;
use davidhirtz\yii2\timeago\TimeagoColumn;
use Override;
use Stringable;
use Yii;
use yii\db\ActiveRecordInterface;
use yii\helpers\Url;

/**
 * @extends GridView<File>
 * @property FileActiveDataProvider $dataProvider
 */
class FileGridView extends GridView
{
    use ModuleTrait;

    /**
     * @var Folder|null the folder to display files from
     */
    public ?Folder $folder = null;

    /**
     * @var AssetParentInterface|null the parent record linked via Asset
     */
    public ?AssetParentInterface $parent = null;

    #[Override]
    public function init(): void
    {
        $this->id = $this->getId(false) ?? 'files';
        $this->folder ??= $this->dataProvider->folder;

        if ($this->parent) {
            $fileIds = array_map(intval(...), array_column($this->parent->assets, 'file_id'));

            $this->rowOptions = fn (File $file) => [
                'id' => $this->getRowId($file),
                'class' => in_array($file->id, $fileIds, true) ? 'is-selected' : null,
            ];
        }

        if (!$this->columns) {
            $this->columns = [
                $this->thumbnailColumn(),
                $this->nameColumn(),
                $this->filenameColumn(),
                $this->assetCountColumn(),
                $this->altTextColumn(),
                $this->updatedAtColumn(),
                $this->buttonsColumn(),
            ];
        }

        parent::init();
    }

    protected function initHeader(): void
    {
        $this->header ??= [
            [
                $this->folderDropdown(),
                $this->search->getColumn(),
            ],
        ];
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

    public function thumbnailColumn(): array
    {
        return [
            'class' => FileThumbnailColumn::class,
            'route' => fn (File $file) => $this->getRoute($file),
        ];
    }

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

    public function filenameColumn(): array
    {
        return [
            'attribute' => 'filename',
            'headerOptions' => ['class' => 'd-none d-md-table-cell'],
            'contentOptions' => ['class' => 'd-none d-md-table-cell'],
            'content' => fn (File $file): string => $file->getFilename()
        ];
    }

    public function assetCountColumn(): array
    {
        return [
            'label' => Yii::t('media', 'Assets'),
            'class' => CounterColumn::class,
            'value' => fn (File $file) => $file->getRelatedModelCount(),
            'route' => fn (File $file) => ['/admin/file/update', 'id' => $file->id, '#' => 'assets'],
        ];
    }

    public function altTextColumn(): array
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

                return Link::make()
                    ->href($this->getRoute($file, ['#' => 'assets']))
                    ->icon('check')
                    ->addClass('text-success');
            }
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
            'class' => ButtonsColumn::class,
            'content' => function (File $file): array {
                if ($this->parent) {
                    $route = [
                        'create',
                        strtolower($this->parent->formName()) => $this->parent->getPrimaryKey(),
                        'file' => $file->id,
                    ];

                    return [
                        Button::secondary()
                            ->icon('image')
                            ->href(['/admin/file/update', 'id' => $file->id])
                            ->addClass('d-none d-md-block')
                            ->render(),
                        // Todo
                        Html::a((string)Icon::tag('plus'), $route, [
                            'class' => 'btn btn-primary',
                            'data-ajax' => 'add',
                            'data-target' => '#' . $this->getRowId($file),
                        ]),
                    ];
                }

                return [
                    Button::primary()
                        ->icon('wrench')
                        ->href(['/admin/file/update', 'id' => $file->id])
                        ->addClass('d-none d-md-block')
                        ->render(),
                    new DeleteButton($file),
                ];
            }
        ];
    }

    protected function getUploadFileButton(): ?Stringable
    {
        return new UploadFileButton(
            Yii::t('media', 'Upload Files'),
            $this->getFileUploadRoute(),
            '#' . $this->getId(),
            true,
        );
    }

    protected function getImportFileButton(): ?Stringable
    {
        return new ImportFileButton(
            Yii::t('media', 'Import'),
            $this->getFileUploadRoute(),
        );
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

    public function folderDropdown(): ?FilterDropdown
    {
        $items = $this->folderDropdownItems();

        return count($items) > 1
            ? new FilterDropdown(
                $items,
                Yii::t('media', 'Folders'),
                'folder'
            )
            : null;
    }

    protected function folderDropdownItems(): array
    {
        return ArrayHelper::getColumn(FolderCollection::getAll(), 'name');
    }

    /**
     * @param File $model
     */
    #[Override]
    protected function getRoute(ActiveRecordInterface $model, array $params = []): array|false
    {
        return ['/admin/file/update', 'id' => $model->id, ...$params];
    }
}
