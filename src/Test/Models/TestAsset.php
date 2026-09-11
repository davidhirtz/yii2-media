<?php

declare(strict_types=1);

namespace Hirtz\Media\Test\Models;

use Hirtz\Media\Models\Asset;
use Override;

class TestAsset extends Asset
{
    #[Override]
    public static function getModelClass(): string
    {
        return TestAssetModel::class;
    }

    #[Override]
    public function getPermissionName(string $action): string
    {
        return 'assetUpdate';
    }

    /**
     * The model has no table, so an unpopulated relation resolves to the shared instance rather than a lookup.
     */
    #[Override]
    public function getModel(): TestAssetModel
    {
        if (!$this->isRelationPopulated('model')) {
            $this->populateRelation('model', TestAssetModel::instance());
        }

        /** @var TestAssetModel */
        return parent::getModel();
    }
}
