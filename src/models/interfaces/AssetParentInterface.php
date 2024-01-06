<?php

namespace davidhirtz\yii2\media\models\interfaces;

use davidhirtz\yii2\skeleton\db\ActiveQuery;
use davidhirtz\yii2\skeleton\models\interfaces\TypeAttributeInterface;
use yii\db\ActiveRecordInterface;

/**
 * @property int $id
 * @property AssetInterface[] $assets {@see static::getAssets()}
 * @method string formName()
 */
interface AssetParentInterface extends ActiveRecordInterface, TypeAttributeInterface
{
    public function getAssets(): ActiveQuery;
    public function getAssetSizes(): ?string;
    public function getAssetTransformationNames(): array;
}
