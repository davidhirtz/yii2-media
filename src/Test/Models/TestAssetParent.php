<?php

declare(strict_types=1);

namespace Hirtz\Media\Test\Models;

use Hirtz\Media\Models\Interfaces\AssetParentInterface;
use Hirtz\Media\Models\Traits\AssetParentTrait;
use Hirtz\Skeleton\Db\ActiveQuery;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Models\Interfaces\TypeAttributeInterface;
use Override;

class TestAssetParent extends ActiveRecord implements AssetParentInterface, TypeAttributeInterface
{
    use AssetParentTrait;

    #[Override]
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
