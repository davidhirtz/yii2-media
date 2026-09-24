<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Panels;

use Hirtz\Media\Models\File;
use Hirtz\Media\Test\Images\TestImageProcessor;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Modules\Admin\Widgets\Panels\ServerInfo;
use Yii;

class ServerInfoImageFormatsTest extends TestCase
{
    public function testTheFormatsAreListedWithoutAWarningWhenTheServerWritesThemAll(): void
    {
        File::getModule()->imageProcessor = new TestImageProcessor();

        $html = ServerInfo::make()->render();

        self::assertStringContainsString(Yii::t('media', 'TRANSFORMATION_EXTENSIONS_LABEL'), $html);
        self::assertStringContainsString('<span>AVIF</span> · <span>WEBP</span>', $html);
        self::assertStringNotContainsString('badge-warning', $html);
    }

    public function testAFormatTheServerCannotWriteIsFlagged(): void
    {
        $processor = new TestImageProcessor();
        $processor->unencodable = ['avif'];
        File::getModule()->imageProcessor = $processor;

        $html = ServerInfo::make()->render();

        self::assertStringContainsString('<span class="badge badge-warning">AVIF</span>', $html);
        self::assertStringContainsString(
            Yii::t('media', 'TRANSFORMATION_EXTENSIONS_UNSUPPORTED', ['extensions' => 'AVIF']),
            $html,
        );
    }

    public function testNoRowWithoutTransformationExtensions(): void
    {
        File::getModule()->transformationExtensions = [];

        $html = ServerInfo::make()->render();

        self::assertStringNotContainsString(Yii::t('media', 'TRANSFORMATION_EXTENSIONS_LABEL'), $html);
    }
}
