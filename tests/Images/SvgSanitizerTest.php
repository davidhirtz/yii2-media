<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Images;

use Hirtz\Media\Helpers\ImageSize;
use Hirtz\Media\Images\SvgSanitizer;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Helpers\FileHelper;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Yii;

class SvgSanitizerTest extends TestCase
{
    private string $path;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->path = Yii::getAlias('@runtime/svg-sanitizer') . '/Åsa 東京.svg';
        FileHelper::createDirectory(dirname($this->path));
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory(dirname($this->path));
        parent::tearDown();
    }

    public function testScriptsHandlersAndExternalReferencesAreRemoved(): void
    {
        $svg = $this->sanitize((string)file_get_contents(__DIR__ . '/../Data/malicious.svg'));

        foreach (['<script', 'alert', 'onload', 'onclick', 'foreignObject', 'javascript:', 'evil.example', 'data:text'] as $needle) {
            self::assertStringNotContainsStringIgnoringCase($needle, $svg);
        }

        self::assertSame([120, 60], ImageSize::fromSvg($this->path));
    }

    public function testADrawingKeepsItsShapesTextAndInternalReferences(): void
    {
        $svg = $this->sanitize(<<<SVG
            <?xml version="1.0" encoding="UTF-8"?>
            <!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 40 20">
                <defs><symbol id="dot"><circle cx="5" cy="5" r="5" fill="url(#fade)"/></symbol></defs>
                <use href="#dot"/>
                <use xlink:href="#dot" x="10"/>
                <image width="1" height="1" href="data:image/png;base64,iVBORw0KGgo="/>
                <text>Åsa ąę Привет 漢字 🎉 Mu\u{308}ller</text>
            </svg>
            SVG);

        self::assertStringContainsString('<use href="#dot"', $svg);
        self::assertStringContainsString('xlink:href="#dot"', $svg);
        self::assertStringContainsString('fill="url(#fade)"', $svg);
        self::assertStringContainsString('href="data:image/png;base64,', $svg);
        self::assertStringContainsString("Åsa ąę Привет 漢字 🎉 Mu\u{308}ller", $svg);
        self::assertStringNotContainsString('DOCTYPE', $svg);
        self::assertSame([40, 20], ImageSize::fromSvg($this->path));
    }

    #[DataProvider('notAnSvgDataProvider')]
    public function testWhatLeavesNoSvgIsRefused(string $contents): void
    {
        file_put_contents($this->path, $contents);
        self::assertFalse((new SvgSanitizer())->sanitizeFile($this->path));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notAnSvgDataProvider(): array
    {
        return [
            'text' => ['Привет'],
            'broken markup' => ['<svg xmlns="http://www.w3.org/2000/svg"><rect>'],
            'an undefined entity' => ['<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>'],
            'another root' => ['<script xmlns="http://www.w3.org/2000/svg">alert(1)</script>'],
        ];
    }

    private function sanitize(string $contents): string
    {
        file_put_contents($this->path, $contents);
        self::assertTrue((new SvgSanitizer())->sanitizeFile($this->path));

        return (string)file_get_contents($this->path);
    }
}
