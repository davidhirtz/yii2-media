<?php

namespace davidhirtz\yii2\media\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecordInterface;

/**
 * Class AssetInterface
 * @package davidhirtz\yii2\media\models
 *
 */
interface AssetInterface extends ActiveRecordInterface
{
    /**
     * @return ActiveQuery
     */
    public function getFile();
}