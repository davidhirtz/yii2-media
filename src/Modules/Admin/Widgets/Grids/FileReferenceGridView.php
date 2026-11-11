<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Widgets\Grids;

use Hirtz\Media\Models\FileReference;
use Hirtz\Media\Traits\FilePropertyTrait;
use Hirtz\Skeleton\Widgets\Grids\GridView;
use Hirtz\Skeleton\Widgets\Grids\Traits\ModelReferenceGridTrait;
use Override;
use yii\data\ActiveDataProvider;

/**
 * @extends GridView<FileReference>
 */
class FileReferenceGridView extends GridView
{
    use ModelReferenceGridTrait;
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
        FileReference::populateModelRelations($references, searchable: true);

        foreach ($references as $reference) {
            $reference->populateFileRelation($this->file);
        }

        $this->columns ??= $this->getModelReferenceColumns();

        parent::configure();
    }
}
