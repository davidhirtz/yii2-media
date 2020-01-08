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

    /**
     * @return ActiveQuery
     */
    public function getParent();

    /**
     * @return string the name of the asset count column in related File record
     */
    public static function fileCountAttribute(): string;

    /**
     * @return string the class name of the related asset parent grid
     */
    public static function getAssetParentGridView(): string;
}