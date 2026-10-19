<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models;

use Hirtz\Media\Modules\Admin\Widgets\Grids\AssetGridView;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Media\Widgets\Media;
use Hirtz\Skeleton\Widgets\Grids\Columns\Column;
use Override;
use Stringable;

/**
 * A project may filter the asset's default custom attributes down to the ones it uses, so every one of them is
 * optional: nothing reading the asset may assume `name`, `content`, `alt_text`, `link`, `embed_url`, `loading` or
 * `fetchpriority` is declared.
 */
class AssetWithoutCustomAttributesTest extends TestCase
{
    use MediaFixtureTrait;

    public function testTheAssetDeclaresNone(): void
    {
        $asset = $this->createAsset();

        foreach (['name', 'content', 'alt_text', 'link', 'embed_url', 'loading', 'fetchpriority'] as $attribute) {
            self::assertFalse($asset->hasAttribute($attribute));
            self::assertNull($asset->getVisibleAttribute($attribute));
        }
    }

    public function testTheAltTextFallsBackToTheFile(): void
    {
        self::assertSame('Alt Text 1', $this->createAsset()->getAltText());
    }

    public function testTheSitemapUrlHasNoCaption(): void
    {
        $url = $this->createAsset()->getSitemapUrl();

        self::assertIsArray($url);
        self::assertSame('Alt Text 1', $url['title']);
        self::assertArrayNotHasKey('caption', $url);
    }

    public function testThereIsNoEmbedUrl(): void
    {
        self::assertSame('', $this->createAsset()->getFormattedEmbedUrl());
    }

    public function testTheMediaRenders(): void
    {
        self::assertStringContainsString('alt="Alt Text 1"', (string)Media::make()->asset($this->createAsset()));
    }

    public function testTheGridShowsTheFilesAltTextAndNoLinkColumn(): void
    {
        $grid = AssetWithoutCustomAttributesGridView::make();
        $asset = $this->createAsset();

        self::assertStringContainsString('fa-check', (string)$grid->getAltTextColumn()?->renderBody($asset, 0, 0));
        self::assertNull($grid->getUrlIcon($asset->getVisibleAttribute('link'), 'link'));
    }

    private function createAsset(): AssetWithoutCustomAttributes
    {
        $asset = AssetWithoutCustomAttributes::create();
        $asset->status = AssetWithoutCustomAttributes::STATUS_ENABLED;
        $asset->populateFileRelation($this->getFileFromFixture('file-1'));

        return $asset;
    }
}

class AssetWithoutCustomAttributes extends TestAsset
{
    #[Override]
    protected function getDefaultCustomAttributes(): array
    {
        return [];
    }
}

/**
 * @extends AssetGridView<AssetWithoutCustomAttributes>
 */
class AssetWithoutCustomAttributesGridView extends AssetGridView
{
    #[Override]
    public function getAltTextColumn(): ?Column
    {
        return parent::getAltTextColumn();
    }

    #[Override]
    public function getUrlIcon(mixed $url, string $icon): ?Stringable
    {
        return parent::getUrlIcon($url, $icon);
    }
}
