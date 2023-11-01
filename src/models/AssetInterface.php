<?php

namespace davidhirtz\yii2\media\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecordInterface;

/**
 * AssetInterface describes the asset models which are linked to a {@see File}.
 *
 * @property int $file_id
 * @property-read File $file {@see static::getFile}
 */
interface AssetInterface extends ActiveRecordInterface
{
    /**
     * Constants.
     */
    public const TYPE_VIEWPORT_MOBILE = 2;
    public const TYPE_VIEWPORT_DESKTOP = 3;

    /**
     * @return ActiveQuery the query for the related File record
     */
    public function getFile(): ActiveQuery;

    /**
     * @return string the name of the asset count column in the related File record
     */
    public function getFileCountAttribute(): string;

    /**
     * @return class-string the class name of the related asset parent grid
     */
    public function getParentGridView(): string;

    /**
     * @return string
     */
    public function getParentName(): string;
}