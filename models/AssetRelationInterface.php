<?php

namespace davidhirtz\yii2\media\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecordInterface;

/**
 * Class AssetRelationInterface
 * @package davidhirtz\yii2\media\models
 */
interface AssetRelationInterface extends ActiveRecordInterface
{
    /**
     * @return ActiveQuery
     */
    public function getAssets();
}