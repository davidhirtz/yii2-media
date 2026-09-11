<?php

declare(strict_types=1);

namespace Hirtz\Media\Test\Models;

use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Media\Models\Traits\AssetModelTrait;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Models\Interfaces\TypeAttributeInterface;
use Override;

/**
 * Has no table: the asset counts and the admin routes are not what the media tests exercise.
 */
class TestAssetModel extends ActiveRecord implements AssetModelInterface, TypeAttributeInterface
{
    use AssetModelTrait;

    #[Override]
    public function attributes(): array
    {
        return [
            'id',
            'type',
            'asset_count',
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
