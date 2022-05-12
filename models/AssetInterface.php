<?php

namespace davidhirtz\yii2\media\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecordInterface;

/**
 * AssetInterface describes the asset models which are linked to a {@see File}.
 * @property File $file
 */
interface AssetInterface extends ActiveRecordInterface
{
    /**
     * Constants.
     */
    public const TYPE_VIEWPORT_MOBILE = 2;
    public const TYPE_VIEWPORT_DESKTOP = 3;

    /**
     * @return ActiveQuery
     */
    public function getFile();

    /**
     * @return string the name of the asset count column in related File record
     */
    public function getFileCountAttribute(): string;

    /**
     * @return mixed the class name of the related asset parent grid
     */
    public function getParentGridView();

    /**
     * @return string
     */
    public function getParentName(): string;
}