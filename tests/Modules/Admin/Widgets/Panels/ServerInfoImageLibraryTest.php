<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Panels;

use Hirtz\Media\Models\File;
use Hirtz\Media\Test\Images\TestImageProcessor;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Modules\Admin\Widgets\Panels\ServerInfo;
use Override;
use Yii;

class ServerInfoImageLibraryTest extends TestCase
{
    public function testTheLibraryIsListed(): void
    {
        File::getModule()->imageProcessor = new TestImageProcessor();

        $html = ServerInfo::make()->render();

        self::assertStringContainsString(Yii::t('media', 'TRANSFORMATION_LIBRARY_LABEL'), $html);
        self::assertMatchesRegularExpression('/ImageMagick \d+\.\d+\.\d+-\d+/', $html);
        self::assertStringNotContainsString('TRANSFORMATION_QUALITY', $html);
    }

    public function testAQualityEncodedWithGdIsNamed(): void
    {
        $processor = new TestImageProcessor();
        $processor->qualityIgnored = ['webp'];
        File::getModule()->imageProcessor = $processor;

        $html = ServerInfo::make()->render();

        self::assertStringContainsString(
            Yii::t('media', 'TRANSFORMATION_QUALITY_GD_TEXT', ['extensions' => 'WEBP']),
            $html,
        );
        self::assertStringNotContainsString('badge-warning', $html);
    }

    public function testAQualityNothingAppliesIsFlagged(): void
    {
        $processor = new class () extends TestImageProcessor {
            #[Override]
            public function canEncodeWithGd(string $extension): bool
            {
                return false;
            }
        };

        $processor->qualityIgnored = ['webp'];
        File::getModule()->imageProcessor = $processor;

        $html = ServerInfo::make()->render();

        self::assertStringContainsString('<span class="badge badge-warning">ImageMagick', $html);
        self::assertStringContainsString(
            Yii::t('media', 'TRANSFORMATION_QUALITY_IGNORED_TEXT', ['extensions' => 'WEBP']),
            $html,
        );
    }
}
