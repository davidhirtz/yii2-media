<?php

namespace davidhirtz\yii2\media\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecordInterface;

interface AssetParentInterface extends ActiveRecordInterface
{
    /**
     * @return ActiveQuery
     */
    public function getAssets();
}