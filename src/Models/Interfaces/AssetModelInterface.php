<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Interfaces;

use davidhirtz\yii2\datetime\DateTime;
use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\Queries\AssetQuery;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Models\Interfaces\AdminModelInterface;
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
interface AssetModelInterface extends ActiveRecordInterface, AdminModelInterface
{
    /**
     * @return class-string<Asset>
     */
    public function getAssetClass(): string;

    /**
     * Whether this record has assets — the installation's flag, the type's {@see AssetModelTypeInterface::allowsAssets()}
     * and whatever the record itself says, answered in one place so no caller has to remember the others.
     */
    public function allowsAssets(): bool;

    /**
     * @return AssetQuery<Asset>
     */
    public function getAssets(): AssetQuery;

    public function updateAssetCount(): int;

    /**
     * {@see \yii\db\BaseActiveRecord::updateAttributes()}: an asset touches its owner's `updated_at` without
     * saving the owner.
     *
     * @param array<int|string, mixed> $attributes
     * @return int
     */
    public function updateAttributes($attributes);

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
