<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\Fixtures\FileFixture;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Models\Redirect;
use Override;

class FileTest extends TestCase
{
    private Folder $folder;

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function fixtures(): array
    {
        return [
            'folder' => FolderFixture::class,
            'file' => FileFixture::class,
        ];
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        File::getModule()->addTransformation(Transformation::make('square')->width(50)->height(50));
        File::getModule()->addTransformation(Transformation::make('huge')->width(5000)->height(5000));

        $this->folder = Folder::findOne(1);
        FileHelper::createDirectory($this->folder->getUploadPath());
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        parent::tearDown();
    }

    public function testTheFilenameIsTheBasenameAndItsExtension(): void
    {
        $file = $this->createFile('photo', 'jpg');

        self::assertSame('photo.jpg', $file->getFilename());
        self::assertStringEndsWith('default/photo.jpg', $file->getUrl());
        self::assertStringEndsWith('default' . DIRECTORY_SEPARATOR . 'photo.jpg', $file->getFilePath());
    }

    public function testTheUrlCanCarryTheUpdateTimeAsAVersion(): void
    {
        $file = $this->createFile('photo', 'jpg');

        self::assertStringContainsString('?v=', $file->getUrlWithVersion());
    }

    /**
     * Two files cannot share a name in the same folder, so the second one is numbered.
     */
    public function testASecondFileOfTheSameNameIsNumbered(): void
    {
        $this->createFile('photo', 'jpg');

        $second = $this->buildFile('photo', 'jpg');

        self::assertTrue($second->validate());
        self::assertSame('photo_1', $second->basename);
    }

    /**
     * A transformable image and its transformations share a basename, so two of them would collide once converted
     * to the same output format — the check is on the database, not the file system.
     */
    public function testTwoTransformableImagesCannotShareABasename(): void
    {
        $this->createFile('photo', 'jpg');

        $second = $this->buildFile('photo', 'png');

        self::assertTrue($second->validate());
        self::assertSame('photo_1', $second->basename);
    }

    public function testABasenameThatWouldShadowATransformationIsRefused(): void
    {
        $file = File::create();
        $file->loadDefaultValues();
        $file->name = 'Square';
        $file->basename = 'square';
        $file->extension = 'jpg';
        $file->populateFolderRelation($this->folder);

        self::assertFalse($file->insert());
        self::assertArrayHasKey('basename', $file->getErrors());
    }

    public function testOnlyAnImageWithDimensionsIsTransformable(): void
    {
        self::assertTrue($this->createFile('photo', 'jpg')->isTransformableImage());
        self::assertFalse($this->createFile('drawing', 'svg')->isTransformableImage());

        $file = $this->createFile('nodimensions', 'jpg', width: 0, height: 0);

        self::assertFalse($file->hasDimensions());
        self::assertFalse($file->isTransformableImage());
    }

    /**
     * A transformation bigger than the original is not offered, unless it may scale up.
     */
    public function testATransformationLargerThanTheFileIsNotValid(): void
    {
        $file = $this->createFile('photo', 'jpg', width: 100, height: 100);

        self::assertTrue($file->isValidTransformation('square'));
        self::assertFalse($file->isValidTransformation('huge'));
        self::assertFalse($file->isValidTransformation('does-not-exist'));

        self::assertContains('square', $file->getTransformationNames());
        self::assertNotContains('huge', $file->getTransformationNames());
    }

    public function testTheTransformationUrlFollowsTheFolderAndName(): void
    {
        $file = $this->createFile('photo', 'jpg', width: 100, height: 100);

        self::assertStringEndsWith('default/square/photo.jpg', (string)$file->getTransformationUrl('square'));
        self::assertStringEndsWith('default/square/photo.webp', (string)$file->getTransformationUrl('square', 'webp'));

        self::assertNull($file->getTransformationUrl('huge'));
        self::assertNull($file->getTransformationUrl('does-not-exist'));
    }

    public function testTheSrcsetIsKeyedByTheWidthEachTransformationProduces(): void
    {
        $file = $this->createFile('photo', 'jpg', width: 200, height: 100);

        $srcset = $file->getSrcset(['square', 'huge']);

        self::assertSame([50], array_keys($srcset));
        self::assertStringEndsWith('default/square/photo.jpg', $srcset[50]);

        self::assertSame($srcset, $file->getSrcset('square'));
        self::assertSame([], $file->getSrcset(null));
        self::assertSame([], $this->createFile('drawing', 'svg')->getSrcset('square'));
    }

    /**
     * Renaming a file leaves a redirect behind, so a URL already out in the world keeps working.
     */
    public function testRenamingAFileLeavesARedirect(): void
    {
        $file = $this->createFile('photo', 'jpg');
        $previous = $file->getUrl();

        $file->basename = 'renamed';

        self::assertSame(1, $file->update(), print_r($file->getErrors(), true));
        self::assertNotNull(Redirect::findOne(['request_uri' => Url::sanitize($previous)]));
    }

    public function testTheFolderCountsItsFiles(): void
    {
        $before = Folder::findOne(1)->file_count;

        $file = $this->createFile('photo', 'jpg');
        self::assertSame($before + 1, Folder::findOne(1)->file_count);

        $file->delete();
        self::assertSame($before, Folder::findOne(1)->file_count);
    }

    public function testDeletingAFileRemovesItFromTheFileSystem(): void
    {
        $file = $this->createFile('photo', 'jpg');
        $path = $file->getFilePath();

        self::assertFileExists($path);

        $file->delete();
        self::assertFileDoesNotExist($path);
    }

    public function testTheHumanReadableNameDropsTheSeparators(): void
    {
        $file = File::create();

        self::assertSame('My Holiday Photo', $file->humanizeFilename('my-holiday_photo'));
    }

    public function testTheDimensionsAreReportedAsAPair(): void
    {
        $file = $this->createFile('photo', 'jpg', width: 200, height: 100);

        self::assertSame('200 x 100', $file->getDimensions());
        self::assertSame('', $this->createFile('drawing', 'svg', width: 0, height: 0)->getDimensions());
    }

    private function buildFile(
        string $basename,
        string $extension,
        int $width = 1000,
        int $height = 1000,
    ): File {
        $file = File::create();
        $file->loadDefaultValues();
        $file->name = ucfirst($basename);
        $file->basename = $basename;
        $file->extension = $extension;
        $file->width = $width;
        $file->height = $height;
        $file->populateFolderRelation($this->folder);

        return $file;
    }

    private function createFile(
        string $basename,
        string $extension,
        int $width = 1000,
        int $height = 1000,
    ): File {
        $path = $this->folder->getUploadPath() . "$basename.$extension";
        $this->writeImage($path, $extension, $width, $height);

        $file = $this->buildFile($basename, $extension, $width, $height);
        $file->size = filesize($path);

        self::assertTrue($file->insert(), print_r($file->getErrors(), true));

        $written = $file->getFilePath();

        if (!is_file($written)) {
            $this->writeImage($written, $extension, $width, $height);
        }

        return $file;
    }

    /**
     * The model opens an upload to read and rotate it, so a placeholder has to be a real image.
     */
    private function writeImage(string $path, string $extension, int $width, int $height): void
    {
        if ($extension === 'svg') {
            file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
            return;
        }

        $image = imagecreatetruecolor(max($width, 1), max($height, 1));
        imagejpeg($image, $path);
        imagedestroy($image);
    }
}
