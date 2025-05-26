<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\models\interfaces;

use davidhirtz\yii2\skeleton\db\ActiveQuery;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
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

    public function getAssetSizes(): ?string;

    public function getAssetTransformationNames(): array;
}
