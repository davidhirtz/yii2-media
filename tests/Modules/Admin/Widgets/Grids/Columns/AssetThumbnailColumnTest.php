<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Grids\Columns;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Modules\Admin\Widgets\Grids\Columns\AssetThumbnailColumn;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;

class AssetThumbnailColumnTest extends TestCase
{
    use MediaFixtureTrait;

    public function testTheThumbnailIsWrappedInTheLink(): void
    {
        $body = (string)AssetThumbnailColumn::make()
            ->url(fn (Asset $asset): array|false => $asset->getAdminRoute())
            ->renderBody($this->createAsset(), 0, 0);

        self::assertStringContainsString('<a href="/admin/media/asset/update?id=1"><img ', $body);
    }

    public function testTheThumbnailIsRenderedWithoutALink(): void
    {
        $body = (string)AssetThumbnailColumn::make()
            ->url(fn (Asset $asset): null => null)
            ->renderBody($this->createAsset(), 0, 0);

        self::assertStringNotContainsString('<a ', $body);
        self::assertStringContainsString('<img ', $body);
    }

    private function createAsset(): TestAsset
    {
        $asset = TestAsset::create();
        $asset->id = 1;
        $asset->populateFileRelation($this->getFileFromFixture('file-1'));

        return $asset;
    }
}
