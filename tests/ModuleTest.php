<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests;

use Hirtz\Media\Models\File;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Skeleton\Helpers\FileHelper;
use Override;

class ModuleTest extends TestCase
{
    use MediaFileTrait;

    #[Override]
    protected function setUp(): void
    {
        $config = require(__DIR__ . '/../config/test.php');
        $config['modules']['media']['transformations'] = [
            Transformation::make('400')->width(400),
            Transformation::make('medium')->width(800),
            Transformation::make('1200')->width(1200),
        ];

        $this->config = $config;
        parent::setUp();
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        parent::tearDown();
    }

    /**
     * A numeric name is an `int` key, which array unpacking would renumber when the defaults are merged in.
     */
    public function testANumericNameSurvivesTheDefaults(): void
    {
        $module = File::getModule();

        self::assertSame(['admin', '400', 'medium', 'og', '1200'], $module->getTransformationNames());
        self::assertSame(400, $module->getTransformation('400')?->getWidth());
        self::assertSame(1200, $module->getTransformation('1200')?->getWidth());
        self::assertNotNull($module->getTransformation('admin'));

        $this->folder = $this->createFolder('Uploads', 'uploads');
        $file = $this->buildFile('photo', width: 1600, height: 900);

        self::assertTrue($file->isValidTransformation('400'));
        self::assertStringEndsWith('/uploads/400/photo.jpg', (string)$file->getTransformationUrl('400'));
    }
}
