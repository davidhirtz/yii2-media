<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models\CustomAttributes;

use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\TestCase;
use Yii;

class EmbedUrlCustomAttributeTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Yii::$app->getI18n()->setLanguages(['en-US', 'de']);
    }

    public function testEmptyEmbedUrl(): void
    {
        self::assertSame('', $this->createAsset()->getFormattedEmbedUrl());
    }

    public function testAPhoneNumberOrAnEmailIsNoEmbedUrl(): void
    {
        $asset = $this->createAsset();
        $asset->embed_url = 'tel:+4930123456';

        self::assertFalse($asset->validate(['embed_url']));

        $asset->embed_url = 'mailto:info@example.com';

        self::assertFalse($asset->validate(['embed_url']));
    }

    public function testYoutubeEmbedUrl(): void
    {
        $asset = $this->createAsset();
        $asset->embed_url = 'https://www.youtube.com/watch?v=jNQXAC9IVRw';

        self::assertTrue($asset->validate(['embed_url']));
        self::assertSame('https://www.youtube.com/embed/jNQXAC9IVRw', $asset->embed_url);

        self::assertSame(
            'https://www.youtube.com/embed/jNQXAC9IVRw?autoplay=1&disablekb=1&rel=0',
            $asset->getFormattedEmbedUrl()
        );
    }

    public function testYoutubeEmbedUrlWhoseVideoIsNotTheFirstParameter(): void
    {
        $asset = $this->createAsset();
        $asset->embed_url = 'https://www.youtube.com/watch?feature=share&v=jNQXAC9IVRw&t=30s';

        self::assertTrue($asset->validate(['embed_url']));
        self::assertSame('https://www.youtube.com/embed/jNQXAC9IVRw?feature=share&t=30s', $asset->embed_url);
    }

    public function testYoutubeEmbedUrlKeepsTheParametersAfterTheVideo(): void
    {
        $asset = $this->createAsset();
        $asset->embed_url = 'https://m.youtube.com/watch?v=jNQXAC9IVRw&list=PL1';

        self::assertTrue($asset->validate(['embed_url']));
        self::assertSame('https://m.youtube.com/embed/jNQXAC9IVRw?list=PL1', $asset->embed_url);
    }

    public function testYoutubeShortsEmbedUrl(): void
    {
        $asset = $this->createAsset();
        $asset->embed_url = 'https://www.youtube.com/shorts/jNQXAC9IVRw';

        self::assertTrue($asset->validate(['embed_url']));
        self::assertSame('https://www.youtube.com/embed/jNQXAC9IVRw', $asset->embed_url);
    }

    public function testYoutubeShortEmbedUrl(): void
    {
        $asset = $this->createAsset();
        $asset->embed_url = 'https://youtu.be/jNQXAC9IVRw?feature=youtu.be';

        self::assertTrue($asset->validate(['embed_url']));
        self::assertSame('https://www.youtube.com/embed/jNQXAC9IVRw?feature=youtu.be', $asset->embed_url);
    }

    public function testYoutubeLiveEmbedUrl(): void
    {
        $asset = $this->createAsset();
        $asset->embed_url = 'https://www.youtube.com/live/jNQXAC9IVRw';

        self::assertTrue($asset->validate(['embed_url']));
        self::assertSame('https://www.youtube.com/embed/jNQXAC9IVRw', $asset->embed_url);
    }

    public function testVimeoEmbedUrl(): void
    {
        $asset = $this->createAsset();
        $asset->embed_url_de = 'https://vimeo.com/123456789';

        self::assertTrue($asset->validate(['embed_url_de']));
        self::assertSame('https://player.vimeo.com/video/123456789', $asset->embed_url_de);

        self::assertSame(
            'https://player.vimeo.com/video/123456789?autoplay=1&dnt=1',
            $asset->getFormattedEmbedUrl('de')
        );
    }

    public function testUnlistedVimeoEmbedUrlKeepsItsPrivacyHash(): void
    {
        $asset = $this->createAsset();
        $asset->embed_url = 'https://vimeo.com/123456789/abcdef1234?share=copy';

        self::assertTrue($asset->validate(['embed_url']));
        self::assertSame('https://player.vimeo.com/video/123456789?h=abcdef1234', $asset->embed_url);

        self::assertSame(
            'https://player.vimeo.com/video/123456789?h=abcdef1234&autoplay=1&dnt=1',
            $asset->getFormattedEmbedUrl()
        );
    }

    protected function createAsset(): TranslatedEmbedUrlAsset
    {
        return TranslatedEmbedUrlAsset::create();
    }
}

/**
 * @property string|null $embed_url_de
 */
class TranslatedEmbedUrlAsset extends TestAsset
{
    public ?array $translatableAttributes = ['embed_url'];
}
