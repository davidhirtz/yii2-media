<?php

declare(strict_types=1);

namespace Hirtz\Media\tests\data\models;

use Hirtz\Media\models\interfaces\AssetParentInterface;
use Hirtz\Media\models\traits\AssetParentTrait;
use Hirtz\Skeleton\db\ActiveQuery;
use Hirtz\Skeleton\db\ActiveRecord;
use Hirtz\Skeleton\models\interfaces\TypeAttributeInterface;

class TestAssetParent extends ActiveRecord implements AssetParentInterface, TypeAttributeInterface
{
    use AssetParentTrait;

    #[\Override]
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
