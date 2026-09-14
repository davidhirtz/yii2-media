<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\FileTransformation;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Skeleton\Helpers\FileHelper;
use Override;

/**
 * A transformation is derived from the file's content, so moving or renaming a file may carry its derivatives
 * along. Dropping them instead costs an image operation per transformation on the next request for each.
 */
class FileTransformationMoveTest extends TestCase
{
    private Folder $folder;
    private Folder $target;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->logger->isRecording = true;

        $module = File::getModule();
        $module->addTransformation(Transformation::make('square')->width(50)->height(50));
        $module->addTransformation(Transformation::make('wide')->width(80)->keepAspectRatio());

        $this->folder = $this->createFolder('Uploads', 'uploads');
        $this->target = $this->createFolder('Archive', 'archive');
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        parent::tearDown();
    }

    public function testAFolderChangeCarriesTheTransformations(): void
    {
        $file = $this->createFile('photo');
        $square = $this->createTransformation($file, 'square');
        $wide = $this->createTransformation($file, 'wide');

        $previousPaths = [$square->getFilePath(), $wide->getFilePath()];

        $file->populateFolderRelation($this->target);
        self::assertTrue((bool)$file->update(), print_r($file->getErrors(), true));

        self::assertSame(2, File::findOne($file->id)->transformation_count);
        self::assertCount(2, FileTransformation::findAll(['file_id' => $file->id]));

        foreach ([$square, $wide] as $index => $transformation) {
            $transformation->refresh();

            self::assertFileDoesNotExist($previousPaths[$index]);
            self::assertFileExists($transformation->getFilePath());
            self::assertStringContainsString('archive', $transformation->getFilePath());
        }
    }

    public function testARenameCarriesTheTransformations(): void
    {
        $file = $this->createFile('photo');
        $transformation = $this->createTransformation($file, 'square');
        $previousPath = $transformation->getFilePath();

        $file->basename = 'renamed';
        self::assertTrue((bool)$file->update(), print_r($file->getErrors(), true));

        $transformation->refresh();

        self::assertFileDoesNotExist($previousPath);
        self::assertFileExists($transformation->getFilePath());
        self::assertStringEndsWith('renamed.jpg', $transformation->getFilePath());
        self::assertSame(1, File::findOne($file->id)->transformation_count);
    }

    /**
     * A record whose file is gone would keep the on-demand route from recreating it — the insert fails its own
     * uniqueness rule — so it is deleted rather than carried along.
     */
    public function testATransformationWithoutItsFileIsDropped(): void
    {
        $file = $this->createFile('photo');
        $transformation = $this->createTransformation($file, 'square');

        FileHelper::unlink($transformation->getFilePath());

        $file->populateFolderRelation($this->target);
        self::assertTrue((bool)$file->update(), print_r($file->getErrors(), true));

        self::assertNull(FileTransformation::findOne($transformation->id));
        self::assertSame(0, File::findOne($file->id)->transformation_count);
    }

    /**
     * Resizing the image rewrites the source, so the derivatives no longer describe it.
     */
    public function testAChangedImageStillDropsTheTransformations(): void
    {
        $file = $this->createFile('photo', 200, 100);
        $this->createTransformation($file, 'square');

        $file->width = 100;
        $file->height = 50;

        self::assertTrue((bool)$file->update(), print_r($file->getErrors(), true));

        self::assertSame(0, File::findOne($file->id)->transformation_count);
        self::assertEmpty(FileTransformation::findAll(['file_id' => $file->id]));
    }

    private function getLoggedErrors(): string
    {
        return implode("\n", array_map(
            fn (array $message): string => (string)$message[0],
            array_filter(
                $this->logger->messages,
                fn (array $message): bool => is_string($message[0]) && $message[1] === \yii\log\Logger::LEVEL_ERROR
            )
        ));
    }

    private function createTransformation(File $file, string $name): FileTransformation
    {
        $transformation = FileTransformation::create();
        $transformation->name = $name;
        $transformation->populateFileRelation($file);

        self::assertTrue($transformation->insert(), $this->getLoggedErrors());

        return $transformation;
    }

    private function createFolder(string $name, string $path): Folder
    {
        $folder = Folder::create();
        $folder->loadDefaultValues();
        $folder->name = $name;
        $folder->path = $path;

        self::assertTrue($folder->insert(), print_r($folder->getErrors(), true));

        FileHelper::createDirectory($folder->getUploadPath());

        return $folder;
    }

    private function createFile(string $basename, int $width = 200, int $height = 100): File
    {
        $path = $this->folder->getUploadPath() . "$basename.jpg";

        $image = imagecreatetruecolor($width, $height);
        imagejpeg($image, $path);
        imagedestroy($image);

        $file = File::create();
        $file->loadDefaultValues();
        $file->name = ucfirst($basename);
        $file->basename = $basename;
        $file->extension = 'jpg';
        $file->width = $width;
        $file->height = $height;
        $file->size = filesize($path);
        $file->populateFolderRelation($this->folder);

        self::assertTrue($file->insert(), print_r($file->getErrors(), true));

        return $file;
    }
}
