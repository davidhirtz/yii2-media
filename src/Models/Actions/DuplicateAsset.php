<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Actions;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Skeleton\Models\Actions\DuplicateActiveRecord;
use Override;

/**
 * @extends DuplicateActiveRecord<Asset>
 */
class DuplicateAsset extends DuplicateActiveRecord
{
    protected ?AssetModelInterface $assetModel;

    public function __construct(
        protected Asset $asset,
        ?AssetModelInterface $model = null,
        protected bool $shouldUpdateModelAfterInsert = true,
        array $attributes = []
    ) {
        $this->assetModel = $model;

        parent::__construct($asset, [
            'status' => Asset::STATUS_DRAFT,
            ...$attributes,
        ]);
    }

    #[Override]
    protected function beforeDuplicate(): bool
    {
        $this->duplicate->populateModelRelation(!$this->assetModel || $this->assetModel->getIsNewRecord()
            ? $this->asset->model
            : $this->assetModel);

        $this->duplicate->shouldUpdateModelAfterInsert = $this->shouldUpdateModelAfterInsert;

        return parent::beforeDuplicate();
    }
}
