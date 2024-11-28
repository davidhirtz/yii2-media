<?php

namespace davidhirtz\yii2\media\tests\data\models;

use davidhirtz\yii2\media\models\interfaces\AssetParentInterface;
use davidhirtz\yii2\media\models\traits\AssetParentTrait;
use davidhirtz\yii2\skeleton\db\ActiveQuery;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use davidhirtz\yii2\skeleton\models\interfaces\TypeAttributeInterface;

class TestAssetParent extends ActiveRecord implements AssetParentInterface, TypeAttributeInterface
{
    use AssetParentTrait;

    public function attributes(): array
    {
        return [
            'id',
            'type',
        ];
    }

    public function getAssets(): ActiveQuery
    {
        return $this->hasMany(TestAsset::class, ['parent_id' => 'id']);
    }
}
