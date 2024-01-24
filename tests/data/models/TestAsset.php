<?php

namespace davidhirtz\yii2\media\tests\data\models;

use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use davidhirtz\yii2\media\models\interfaces\AssetParentInterface;
use davidhirtz\yii2\media\models\traits\AssetTrait;
use davidhirtz\yii2\skeleton\db\ActiveRecord;

class TestAsset extends ActiveRecord implements AssetInterface
{
    use AssetTrait;

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

    public function rules(): array
    {
        return [
            [
                ['alt_text'],
                'string',
            ],
        ];
    }

    public function getFileCountAttribute(): string
    {
        return 'asset_count';
    }

    public function getParent(): AssetParentInterface
    {
        return TestAssetParent::instance();
    }

    public function getParentGridView(): string
    {
        return '';
    }

    public function getParentName(): string
    {
        return 'Test Parent';
    }
}
