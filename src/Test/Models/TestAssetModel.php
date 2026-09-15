<?php

declare(strict_types=1);

namespace Hirtz\Media\Test\Models;

use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Media\Models\Traits\AssetModelTrait;
use Hirtz\Skeleton\Db\ActiveQuery;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Models\Interfaces\TypeAttributeInterface;
use Hirtz\Skeleton\Models\Traits\AdminModelTrait;
use Override;

/**
 * Has no table: the asset counts and the admin routes are not what the media tests exercise.
 */
class TestAssetModel extends ActiveRecord implements AssetModelInterface, TypeAttributeInterface
{
    use AdminModelTrait;
    use AssetModelTrait;

    /**
     * Emulated, so a lookup by id answers nothing instead of hitting a table that does not exist — which is what
     * lets {@see TestAsset::getModel()} fall back to the shared instance.
     *
     * @return ActiveQuery<static>
     */
    #[Override]
    public static function find(): ActiveQuery
    {
        return parent::find()->emulateExecution();
    }

    #[Override]
    public function attributes(): array
    {
        return [
            'id',
            'type',
            'asset_count',
            // `Asset::afterSave()` and `ReorderAssets` touch the model's timestamp, as every real asset model has one
            'updated_at',
        ];
    }

    public function getAssetClass(): string
    {
        return TestAsset::class;
    }

    public function hasAssetsEnabled(): bool
    {
        return true;
    }

    #[Override]
    public function recalculateAssetCount(): static
    {
        return $this;
    }

    #[Override]
    public function update($runValidation = true, $attributeNames = null): int|false
    {
        return 0;
    }

    public function getAdminRoute(): array|false
    {
        return false;
    }
}
