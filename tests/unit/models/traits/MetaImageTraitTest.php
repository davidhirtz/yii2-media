<?php

declare(strict_types=1);

namespace Hirtz\Media\tests\unit\models\traits;

use Codeception\Test\Unit;
use Hirtz\Media\models\interfaces\AssetInterface;
use Hirtz\Media\models\traits\MetaImageTrait;
use Hirtz\Media\tests\data\models\TestAsset;

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
