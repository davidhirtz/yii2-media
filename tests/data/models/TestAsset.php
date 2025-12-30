<?php

declare(strict_types=1);

namespace Hirtz\Media\tests\data\Models;

use Hirtz\Media\Models\Interfaces\AssetInterface;
use Hirtz\Media\Models\Interfaces\AssetParentInterface;
use Hirtz\Media\Models\Traits\AssetTrait;
use Hirtz\Skeleton\Db\ActiveRecord;

class TestAsset extends ActiveRecord implements AssetInterface
{
    use AssetTrait;

    #[\Override]
    public function attributes(): array
    {
        return [
            'id',
            'type',
            'file_id',
            'parent_id',
            'alt_text',
        ];
    }

    #[\Override]
    public function rules(): array
    {
        return [
            [
                ['alt_text'],
                'string',
            ],
        ];
    }

    public function getFileCountAttributeNames(): array
    {
        return ['asset_count'];
    }

    public function getParent(): AssetParentInterface
    {
        return TestAssetParent::instance();
    }

    /**
     * @return class-string<Panel>
     */
    public function getFilePanelClass(): string
    {
        return Panel::class;
    }
}
