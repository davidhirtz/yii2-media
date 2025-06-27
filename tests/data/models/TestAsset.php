<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\tests\data\models;

use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use davidhirtz\yii2\media\models\interfaces\AssetParentInterface;
use davidhirtz\yii2\media\models\traits\AssetTrait;
use davidhirtz\yii2\skeleton\db\ActiveRecord;
use davidhirtz\yii2\skeleton\widgets\bootstrap\Panel;

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

    public function getFilePanelClass(): string
    {
        return Panel::class;
    }
}
