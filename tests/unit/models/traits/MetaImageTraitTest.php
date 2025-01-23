<?php

namespace davidhirtz\yii2\media\tests\unit\models\traits;

use Codeception\Test\Unit;
use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use davidhirtz\yii2\media\models\traits\MetaImageTrait;
use davidhirtz\yii2\media\tests\data\models\TestAsset;

class MetaImageTraitTest extends Unit
{
    public function testMetaImageTypeOptions(): void
    {
        $model = TestMetaImageAsset::create();
        self::assertArrayHasKey(AssetInterface::TYPE_META_IMAGE, $model::getTypes());
    }
}

class TestMetaImageAsset extends TestAsset
{
    use MetaImageTrait;

    public function isSectionAsset(): bool
    {
        return false;
    }
}
