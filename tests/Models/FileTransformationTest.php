<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\FileTransformation;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Skeleton\Helpers\FileHelper;
use Override;

/**
 * The transformations are written to the file system, so each test works in its own upload directory and takes it
 * down again.
 */
class FileTransformationTest extends TestCase
{
    use MediaFileTrait;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->logger->isRecording = true;

        $module = File::getModule();
        $module->addTransformation(Transformation::make('square')->width(50)->height(50));
        $module->addTransformation(Transformation::make('wide')->width(80)->keepAspectRatio());

        $this->folder = $this->createFolder('Uploads', 'uploads');
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        parent::tearDown();
    }

    public function testATransformationIsWrittenToTheFileSystem(): void
    {
        $file = $this->createFile('photo', width: 200, height: 100);

        $transformation = FileTransformation::create();
        $transformation->name = 'square';
        $transformation->populateFileRelation($file);

        self::assertTrue($transformation->insert(), $this->getLoggedErrors());

        self::assertFileExists($transformation->getFilePath());
        self::assertSame(50, $transformation->width);
        self::assertSame(50, $transformation->height);
        self::assertGreaterThan(0, $transformation->size);

        // the transformation inherits the file's own extension when it names none
        self::assertSame('jpg', $transformation->extension);
    }

    public function testAKeptAspectRatioOnlyConstrainsTheWidth(): void
    {
        $file = $this->createFile('photo', width: 200, height: 100);
        $transformation = $this->createTransformation($file, 'wide');

        self::assertSame(80, $transformation->width);
        self::assertSame(40, $transformation->height);
    }

    public function testTheFileCountsItsTransformations(): void
    {
        $file = $this->createFile('photo', width: 200, height: 100);

        $this->createTransformation($file, 'square');
        self::assertSame(1, File::findOne($file->id)->transformation_count);

        $transformation = $this->createTransformation($file, 'wide');
        self::assertSame(2, File::findOne($file->id)->transformation_count);

        self::assertSame(1, $transformation->delete());
        self::assertSame(1, File::findOne($file->id)->transformation_count);
    }

    public function testDeletingATransformationRemovesItsFile(): void
    {
        $file = $this->createFile('photo', width: 200, height: 100);
        $transformation = $this->createTransformation($file, 'square');

        $path = $transformation->getFilePath();
        self::assertFileExists($path);

        $transformation->delete();
        self::assertFileDoesNotExist($path);
    }

    /**
     * The same file, transformation and extension is one row and one file on disk.
     */
    public function testTheSameTransformationIsNotCreatedTwice(): void
    {
        $file = $this->createFile('photo', width: 200, height: 100);
        $this->createTransformation($file, 'square');

        $duplicate = FileTransformation::create();
        $duplicate->name = 'square';
        $duplicate->populateFileRelation($file);

        self::assertFalse($duplicate->insert());
        self::assertArrayHasKey('name', $duplicate->getErrors());
    }

    public function testTheSameTransformationInAnotherFormatIsItsOwnRow(): void
    {
        $file = $this->createFile('photo', width: 200, height: 100);

        $jpg = $this->createTransformation($file, 'square');
        $webp = $this->createTransformation($file, 'square', 'webp');

        self::assertNotSame($jpg->id, $webp->id);
        self::assertNotSame($jpg->getFilePath(), $webp->getFilePath());

        self::assertTrue($webp->isExtensionTransformation());
        self::assertFalse($jpg->isExtensionTransformation());

        self::assertSame('square (webp)', $webp->getDisplayName());
        self::assertSame('square', $jpg->getDisplayName());
    }

    public function testATransformationNameThatIsNotConfiguredIsRefused(): void
    {
        $file = $this->createFile('photo', width: 200, height: 100);

        $transformation = FileTransformation::create();
        $transformation->name = 'does-not-exist';
        $transformation->populateFileRelation($file);

        self::assertFalse($transformation->insert());
        self::assertArrayHasKey('name', $transformation->getErrors());
    }

    public function testAFileThatIsNotATransformableImageIsRefused(): void
    {
        $file = $this->createFile('drawing', 'svg');

        $transformation = FileTransformation::create();
        $transformation->name = 'square';
        $transformation->populateFileRelation($file);

        self::assertFalse($transformation->insert());
        self::assertArrayHasKey('file_id', $transformation->getErrors());
    }

    /**
     * A listener can refuse the transformation, which leaves the record unsaved rather than writing a broken file.
     */
    public function testAListenerCanRefuseTheTransformation(): void
    {
        $file = $this->createFile('photo', width: 200, height: 100);

        $transformation = FileTransformation::create();
        $transformation->name = 'square';
        $transformation->populateFileRelation($file);

        $transformation->on(FileTransformation::EVENT_BEFORE_TRANSFORMATION, function ($event): void {
            $event->isValid = false;
        });

        self::assertFalse($transformation->insert());
        self::assertFileDoesNotExist($transformation->getFilePath());
    }

    public function testTheUrlAndPathFollowTheFolderAndName(): void
    {
        $file = $this->createFile('photo', width: 200, height: 100);
        $transformation = $this->createTransformation($file, 'square');

        self::assertStringEndsWith("{$this->folder->path}/square/photo.jpg", $transformation->getFileUrl());
        self::assertStringEndsWith("{$this->folder->path}/square/photo.webp", $transformation->getFileUrl('webp'));

        self::assertStringEndsWith(
            $this->folder->path . DIRECTORY_SEPARATOR . 'square' . DIRECTORY_SEPARATOR,
            $transformation->getUploadPath()
        );
    }

    private function getLoggedErrors(): string
    {
        return implode("\n", array_map(
            fn (array $message): string => (string)$message[0],
            array_filter(
                $this->logger->messages,
                fn (array $m): bool => is_string($m[0]) && $m[1] === \yii\log\Logger::LEVEL_ERROR
            )
        ));
    }

    private function createTransformation(File $file, string $name, ?string $extension = null): FileTransformation
    {
        $transformation = FileTransformation::create();
        $transformation->name = $name;
        $transformation->extension = $extension;
        $transformation->populateFileRelation($file);

        // a failed transformation is logged rather than added to the model, so the log is what says why
        self::assertTrue($transformation->insert(), $this->getLoggedErrors());

        return $transformation;
    }
}
