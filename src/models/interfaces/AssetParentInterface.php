<?php

namespace davidhirtz\yii2\media\models\interfaces;

use davidhirtz\yii2\skeleton\db\ActiveQuery;
use yii\db\ActiveRecordInterface;

interface AssetParentInterface extends ActiveRecordInterface
{
    public function getAssets(): ActiveQuery;
}