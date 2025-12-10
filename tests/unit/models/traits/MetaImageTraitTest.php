<?php

declare(strict_types=1);

namespace Hirtz\Media\tests\unit\Models\Traits;

use Codeception\Test\Unit;
use Hirtz\Media\Models\interfaces\AssetInterface;
use Hirtz\Media\Models\Traits\MetaImageTrait;
use Hirtz\Media\tests\data\Models\TestAsset;

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
