<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Interfaces;

use Hirtz\Skeleton\Db\ActiveQuery;
use Hirtz\Skeleton\Db\ActiveRecord;
use yii\db\ActiveRecordInterface;

/**
 * @property int $id
 * @property AssetInterface[] $assets {@see static::getAssets()}
 *
 * @phpstan-require-extends ActiveRecord
 */
interface AssetParentInterface extends ActiveRecordInterface
{
    public function getAssets(): ActiveQuery;

    public function hasAssetsEnabled(): bool;

    public function getAssetSizes(): ?string;

    public function getAssetTransformationNames(): array;

    public function getParamName(): string;
}
