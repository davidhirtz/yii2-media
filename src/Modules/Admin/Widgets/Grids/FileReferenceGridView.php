<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids;

use Hirtz\Media\Models\FileReference;
use Hirtz\Media\Traits\FilePropertyTrait;
use Hirtz\Skeleton\Models\Interfaces\AdminModelInterface;
use Hirtz\Skeleton\Widgets\Grids\Columns\ButtonColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\Buttons\ViewGridButton;
use Hirtz\Skeleton\Widgets\Grids\Columns\Column;
use Hirtz\Skeleton\Widgets\Grids\Columns\DataColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\LinkColumn;
use Hirtz\Skeleton\Widgets\Grids\Columns\RelativeTimeColumn;
use Hirtz\Skeleton\Widgets\Grids\GridView;
use Override;
use Stringable;
use Yii;
use yii\data\ActiveDataProvider;

/**
 * Every record whose custom attributes use one file, newest first.
 *
 * @extends GridView<FileReference>
 */
class FileReferenceGridView extends GridView
{
    use FilePropertyTrait;

    protected string $layout = '{items}{pager}{footer}';

    #[Override]
    protected function configure(): void
    {
        $this->attributes['id'] ??= 'file-reference-grid-view';

        $this->provider ??= new ActiveDataProvider([
            'query' => FileReference::find()
                ->andWhere(['file_id' => $this->file->id])
                ->orderBy(['updated_at' => SORT_DESC, 'id' => SORT_DESC]),
            'sort' => false,
        ]);

        $references = $this->provider->getModels();
        FileReference::populateModelRelations($references);

        foreach ($references as $reference) {
            $reference->populateFileRelation($this->file);
        }

        $this->columns ??= [
            $this->getModelColumn(),
            $this->getAttributesColumn(),
            $this->getUpdatedAtColumn(),
            $this->getButtonColumn(),
        ];

        parent::configure();
    }

    protected function getModelColumn(): ?Column
    {
        return LinkColumn::make()
            ->property('model_class')
            ->title(Yii::t('media', 'FILE_REFERENCE_MODEL_LABEL'))
            ->value($this->getModelName(...))
            ->url(fn (FileReference $reference): ?array => $this->getModelRoute($reference));
    }

    protected function getModelName(FileReference $reference): string
    {
        $model = $reference->getModel();

        return $model instanceof AdminModelInterface
            ? $model->getAdminName()
            : "$reference->model_class #$reference->model_id";
    }

    protected function getAttributesColumn(): ?Column
    {
        return DataColumn::make()
            ->property('attribute_counts')
            ->title(Yii::t('media', 'FILE_REFERENCE_ATTRIBUTES_LABEL'))
            ->value($this->getAttributesColumnValue(...));
    }

    protected function getAttributesColumnValue(FileReference $reference): string
    {
        $labels = [];

        foreach ($reference->getAttributeLabelCounts() as $label => $count) {
            $labels[] = $count > 1 ? "$label ({$count}×)" : $label;
        }

        return implode(', ', $labels);
    }

    protected function getUpdatedAtColumn(): ?Column
    {
        return RelativeTimeColumn::make()
            ->property('updated_at');
    }

    protected function getButtonColumn(): ?Column
    {
        return ButtonColumn::make()
            ->content($this->getButtonColumnContent(...));
    }

    /**
     * @return list<Stringable>
     */
    protected function getButtonColumnContent(FileReference $reference): array
    {
        $route = $this->getModelRoute($reference);
        return $route ? [ViewGridButton::make()->url($route)] : [];
    }

    /**
     * @return array<int|string, mixed>|null
     */
    protected function getModelRoute(FileReference $reference): ?array
    {
        $model = $reference->getModel();

        if (!$model instanceof AdminModelInterface || !$this->webuser->can($model->getPermissionName())) {
            return null;
        }

        return $model->getAdminRoute() ?: null;
    }
}
