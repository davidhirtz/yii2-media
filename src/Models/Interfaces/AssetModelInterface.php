<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Interfaces;

use davidhirtz\yii2\datetime\DateTime;
use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\Queries\AssetQuery;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Models\Interfaces\AdminRouteInterface;
use yii\db\ActiveRecordInterface;

/**
 * A model that has assets.
 *
 * @property int $id
 * @property int $asset_count
 * @property DateTime|null $updated_at
 * @property-read Asset[] $assets {@see static::getAssets()}
 *
 * @phpstan-require-extends ActiveRecord
 */
interface AssetModelInterface extends ActiveRecordInterface, AdminRouteInterface
{
    /**
     * @return class-string<Asset>
     */
    public function getAssetClass(): string;

    public function hasAssetsEnabled(): bool;

    /**
     * @return AssetQuery<Asset>
     */
    public function getAssets(): AssetQuery;

    public function recalculateAssetCount(): static;

    /**
     * @param Asset[]|null $assets
     */
    public function populateAssetRelations(?array $assets): void;

    public function getAssetSizes(): ?string;

    /**
     * @return list<string>
     */
    public function getAssetTransformationNames(): array;
}
