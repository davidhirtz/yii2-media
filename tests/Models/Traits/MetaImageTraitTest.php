<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models\Traits;

use Hirtz\Media\Models\Interfaces\AssetInterface;
use Hirtz\Media\Models\Traits\MetaImageTrait;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\TestCase;

class MetaImageTraitTest extends TestCase
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
