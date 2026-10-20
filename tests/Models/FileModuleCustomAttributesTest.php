<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models;

use Hirtz\Media\Models\File;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Models\CustomAttributes\CustomAttribute;
use Hirtz\Skeleton\Models\CustomAttributes\TextCustomAttribute;
use Override;

class FileModuleCustomAttributesTest extends TestCase
{
    use MediaFileTrait;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $module = File::getModule();
        $module->transformationExtensions = ['webp'];
        $module->fileCustomAttributes[] = fn (File $file): array => [
            TextCustomAttribute::make('credit')->visible(fn (): bool => $file->extension === 'jpg'),
        ];

        $this->folder = $this->createFolder('Uploads', 'uploads');
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        parent::tearDown();
    }

    public function testTheModuleAttributesFollowTheQualityAttributes(): void
    {
        $names = array_map(
            fn (CustomAttribute $attribute): string => $attribute->name,
            $this->createFile('pic')->getCustomAttributes(),
        );

        self::assertSame(['transformation_quality_webp', 'credit'], $names);
    }

    public function testTheClosureReceivesTheFile(): void
    {
        self::assertContains('credit', $this->createFile('pic')->safeAttributes());
        self::assertNotContains('credit', $this->buildFile('doc', 'pdf', 0, 0)->safeAttributes());
    }

    public function testAModuleAttributeIsSaved(): void
    {
        $file = $this->createFile('pic');
        $file->load(['File' => ['credit' => 'Åsa Müller']]);

        self::assertSame(1, $file->update(), print_r($file->getErrors(), true));
        self::assertSame('Åsa Müller', File::findOne($file->id)?->getAttribute('credit'));
    }
}
