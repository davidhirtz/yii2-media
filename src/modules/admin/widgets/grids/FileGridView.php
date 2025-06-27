<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\modules\admin\widgets\grids;

use davidhirtz\yii2\media\assets\AdminAsset;
use davidhirtz\yii2\media\models\collections\FolderCollection;
use davidhirtz\yii2\media\models\File;
use davidhirtz\yii2\media\models\Folder;
use davidhirtz\yii2\media\models\interfaces\AssetParentInterface;
use davidhirtz\yii2\media\modules\admin\data\FileActiveDataProvider;
use davidhirtz\yii2\media\modules\admin\widgets\grids\columns\FileThumbnailColumn;
use davidhirtz\yii2\media\modules\admin\widgets\grids\traits\UploadTrait;
use davidhirtz\yii2\media\modules\ModuleTrait;
use davidhirtz\yii2\skeleton\helpers\ArrayHelper;
use davidhirtz\yii2\skeleton\helpers\Html;
use davidhirtz\yii2\skeleton\html\Icon;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grids\columns\CounterColumn;
use davidhirtz\yii2\skeleton\modules\admin\widgets\grids\GridView;
use davidhirtz\yii2\skeleton\widgets\bootstrap\ButtonDropdown;
use davidhirtz\yii2\timeago\TimeagoColumn;
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
    use UploadTrait;

    /**
     * @var Folder|null the folder to display files from
     */
    public ?Folder $folder = null;

    /**
     * @var AssetParentInterface|null the parent record linked via Asset
     */
    public ?AssetParentInterface $parent = null;

    #[\Override]
    public function init(): void
    {
        $this->id = $this->getId(false) ?? 'files';
        $this->folder ??= $this->dataProvider->folder;

        if ($this->parent) {
            $fileIds = ArrayHelper::getColumn($this->parent->assets, 'file_id');

            $this->rowOptions = fn (File $file) => [
                'id' => $this->getRowId($file),
                'class' => in_array($file->id, $fileIds) ? 'is-selected' : null,
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

        if (Yii::$app->getUser()->can(File::AUTH_FILE_CREATE, ['folder' => $this->folder])) {
            AdminAsset::register($view = $this->getView());
            $view->registerJs('Skeleton.mediaFileImport();');
        }

        parent::init();
    }

    protected function initHeader(): void
    {
        $this->header ??= [
            [
                [
                    'content' => $this->folderDropdown(),
                ],
                $this->search->getColumn(),
            ],
        ];
    }

    protected function initFooter(): void
    {
        $this->footer ??= [
            [
                [
                    'content' => Html::buttons($this->getFooterButtons()),
                    'options' => ['class' => 'col'],
                ],
            ],
        ];
    }

    protected function getFooterButtons(): array
    {
        if (!Yii::$app->getUser()->can(File::AUTH_FILE_CREATE, ['folder' => $this->folder])) {
            return [];
        }

        return [$this->getUploadFileButton(), $this->getImportFileButton()];
    }

    #[\Override]
    public function renderItems(): string
    {
        return Html::tag('div', parent::renderItems(), ['id' => 'dropzone']);
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
        return [
            'attribute' => $this->getModel()->getI18nAttributeName('alt_text'),
            'headerOptions' => ['class' => 'd-none d-md-table-cell text-center'],
            'contentOptions' => ['class' => 'd-none d-md-table-cell text-center'],
            'content' => function (File $file) {
                if (!$file->getI18nAttribute('alt_text')) {
                    return '';
                }

                $route = ['/admin/file/update', 'id' => $file->id, '#' => 'assets'];
                return Html::a((string)Icon::tag('check'), $route, ['class' => 'text-success']);
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
            'contentOptions' => ['class' => 'text-right text-nowrap'],
            'content' => function (File $file): string {
                $buttons = [
                    Html::a((string)Icon::tag($this->parent ? 'image' : 'wrench'), ['/admin/file/update', 'id' => $file->id], [
                        'class' => 'btn btn-' . ($this->parent ? 'secondary' : 'primary') . ' d-none d-md-inline-block',
                    ])
                ];

                if ($this->parent) {
                    $route = [
                        'create',
                        strtolower($this->parent->formName()) => $this->parent->getPrimaryKey(),
                        'file' => $file->id,
                    ];

                    $buttons[] = Html::a((string)Icon::tag('plus'), $route, [
                        'class' => 'btn btn-primary',
                        'data-ajax' => 'add',
                        'data-target' => '#' . $this->getRowId($file),
                    ]);
                } else {
                    $buttons[] = Html::a((string)Icon::tag('trash'), ['delete', 'id' => $file->id], [
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

    public function folderDropdown(): string
    {
        if (!FolderCollection::getAll()) {
            return '';
        }

        return ButtonDropdown::widget([
            'label' => $this->folder ? $this->folder->name : Yii::t('media', 'Folders'),
            'items' => $this->folderDropdownItems(),
            'paramName' => 'folder',
        ]);
    }

    protected function folderDropdownItems(): array
    {
        $items = [];

        foreach (FolderCollection::getAll() as $folder) {
            $items[] = [
                'label' => $folder->name,
                'url' => Url::current(['folder' => $folder->id, 'page' => null]),
            ];
        }

        return $items;
    }

    /**
     * @param File $model
     */
    #[\Override]
    protected function getRoute(ActiveRecordInterface $model, array $params = []): array|false
    {
        return ['/admin/file/update', 'id' => $model->id, ...$params];
    }
}
