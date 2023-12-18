<?php

namespace davidhirtz\yii2\media\models\interfaces;

use davidhirtz\yii2\skeleton\db\ActiveQuery;
use yii\db\ActiveRecordInterface;

/**
 * @property AssetInterface[] $assets {@see static::getAssets()}
 */
interface AssetParentInterface extends ActiveRecordInterface
{
    public function getAssets(): ActiveQuery;

    public function formName(): string;
}
