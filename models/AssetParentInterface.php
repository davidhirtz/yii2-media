<?php

namespace davidhirtz\yii2\media\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecordInterface;

/**
 * Class AssetParentInterface
 * @package davidhirtz\yii2\media\models
 */
interface AssetParentInterface extends ActiveRecordInterface
{
    /**
     * @return ActiveQuery
     */
    public function getAssets();
}