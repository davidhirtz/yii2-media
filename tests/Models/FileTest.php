<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models;

use Closure;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\Fixtures\FileFixture;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Models\Redirect;
use Hirtz\Skeleton\Web\CopiedUploadedFile;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Yii;

class FileTest extends TestCase
{
    use MediaFileTrait;

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

    public function testTheExtensionIsNotMassAssignable(): void
    {
        $file = $this->createFile('photo', 'jpg');
        $file->load(['File' => ['extension' => 'php']]);

        self::assertNotContains('extension', $file->safeAttributes());
        self::assertSame('jpg', $file->extension);
    }

    public function testAnExtensionOutsideLettersAndDigitsIsInvalid(): void
    {
        $file = $this->createFile('photo', 'jpg');
        $file->extension = 'x/../../php';

        self::assertFalse($file->validate(['extension']));
    }

    public function testTheUrlCanCarryTheUpdateTimeAsAVersion(): void
    {
        $file = $this->createFile('photo', 'jpg');

        self::assertStringContainsString('?v=', $file->getUrlWithVersion());
    }

    public function testASecondFileOfTheSameNameIsNumbered(): void
    {
        $this->createFile('photo', 'jpg');

        $second = $this->buildFile('photo', 'jpg');

        self::assertTrue($second->validate(), print_r($second->getErrors(), true));
        self::assertSame('photo_1', $second->basename);
    }

    /**
     * Its transformations share the basename, so two would collide once converted to one format.
     */
    public function testTwoTransformableImagesCannotShareABasename(): void
    {
        $this->createFile('photo', 'jpg');

        $second = $this->buildFile('photo', 'png');

        self::assertTrue($second->validate(), print_r($second->getErrors(), true));
        self::assertSame('photo_1', $second->basename);
    }

    /**
     * A trailing `_<number>` used to be stripped, turning `report_2023` into `report_1`.
     */
    #[DataProvider('numberedBasenameDataProvider')]
    public function testANumberedBasenameKeepsTheNameWhole(string $basename, int $number, string $expected): void
    {
        self::assertSame($expected, File::getNumberedBasename($basename, $number));
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function numberedBasenameDataProvider(): array
    {
        return [
            'plain' => ['photo', 1, 'photo_1'],
            'already numbered' => ['report_2023', 1, 'report_2023_1'],
            'subfolder' => ['2/photo', 7, '2/photo_7'],
            'cache buster' => ['photo@100x200', 3, 'photo@100x200_3'],
            'too long' => [str_repeat('a', 300), 12, str_repeat('a', 247) . '_12'],
        ];
    }

    public function testAnUnresolvableCollisionIsReported(): void
    {
        $this->createFile('photo', 'jpg');

        for ($i = 1; $i <= File::MAX_FILENAME_COLLISIONS; $i++) {
            $this->createFile("photo_$i", 'jpg');
        }

        $file = $this->buildFile('photo', 'jpg');

        self::assertFalse($file->validate());
        self::assertArrayHasKey('basename', $file->getErrors());
    }

    /**
     * `overwriteFiles` overwrites a file on disk, never another record's: the unique key would refuse the insert.
     */
    public function testAnotherRecordsNameIsReportedWhileTheModuleOverwritesFiles(): void
    {
        File::getModule()->overwriteFiles = true;

        $this->createFile('photo', 'jpg');
        $second = $this->buildFile('photo', 'jpg');

        self::assertFalse($second->validate());
        self::assertArrayHasKey('basename', $second->getErrors());
    }

    public function testAFileWithoutARecordIsOverwrittenWhileTheModuleOverwritesFiles(): void
    {
        File::getModule()->overwriteFiles = true;

        $this->writeImage($this->folder->getUploadPath() . 'orphan.jpg');
        $file = $this->buildFile('orphan', 'jpg');

        self::assertTrue($file->validate(), print_r($file->getErrors(), true));
        self::assertSame('orphan', $file->basename);
    }

    public function testACropIsNamedApartFromAnExistingFile(): void
    {
        $file = $this->createFile('pic', 'jpg', 100, 100);
        $this->createFile('pic@50x50', 'png', 50, 50);

        $file->width = 50;
        $file->height = 50;

        self::assertSame(1, $file->update(), print_r($file->getErrors(), true));
        self::assertSame('pic@50x50_1', $file->basename);
        self::assertFileExists($file->getFilePath());
    }

    public function testACropOfALongNameStaysWithinTheColumn(): void
    {
        $file = $this->createFile(str_repeat('a', 246), 'jpg', 100, 100);

        $file->width = 50;
        $file->height = 50;

        self::assertSame(1, $file->update(), print_r($file->getErrors(), true));
        self::assertSame(str_repeat('a', 244) . '@50x50', $file->basename);
        self::assertFileExists($file->getFilePath());
    }

    /**
     * Two transformable images sharing a basename would share their WEBP and AVIF derivatives.
     */
    public function testAReplacementInAnotherFormatIsNamedApartFromATransformableSibling(): void
    {
        $file = $this->createFile('pic', 'gif', 100, 100);
        $this->createFile('pic', 'png', 100, 100);

        $file->extension = 'jpg';

        self::assertTrue($file->validate(), print_r($file->getErrors(), true));
        self::assertSame('pic_1', $file->basename);
    }

    public function testAReplacementOfTheSameNameIsNumbered(): void
    {
        File::getModule()->hashDirectories = false;

        $file = $this->createFile('photo', 'jpg', 100, 100);
        $previous = $file->getFilePath();

        $this->replaceFile($file, 'photo.jpg', 20);

        self::assertSame('photo_1', $file->basename);
        self::assertFileExists($file->getFilePath());
        self::assertFileDoesNotExist($previous);
    }

    /**
     * A name freed by a replacement or a delete never comes back with other content under a URL a cache still holds.
     */
    public function testAnUploadIsStoredInADirectoryNamedAfterItsContent(): void
    {
        $file = $this->createFile('photo', 'jpg', 100, 100);

        $this->replaceFile($file, 'photo.jpg', 20);
        $first = $file->basename;

        self::assertMatchesRegularExpression('#^[0-9a-f]{8}/photo$#', $first);
        self::assertSame(substr($first, 0, 8), $file->getHashDirectory());

        $this->replaceFile($file, 'photo.jpg', 30);

        self::assertNotSame($first, $file->basename);
        self::assertStringEndsWith('/photo', $file->basename);
        self::assertDirectoryDoesNotExist($this->folder->getUploadPath() . dirname($first));
    }

    public function testARenameKeepsTheHashDirectory(): void
    {
        $file = $this->createFile('photo', 'jpg', 100, 100);
        $this->replaceFile($file, 'photo.jpg', 20);
        $directory = $file->getHashDirectory();

        $file->basename = 'Neue Übersicht';

        self::assertSame(1, $file->update(), print_r($file->getErrors(), true));
        self::assertSame("$directory/Neue_Ubersicht", $file->basename);
        self::assertFileExists($file->getFilePath());
    }

    public function testTheDisplayFilenameLeavesOutOnlyAHashDirectory(): void
    {
        $file = $this->buildFile('3f9a2c1b/photo');
        self::assertSame('photo.jpg', $file->getDisplayFilename());

        $file->basename = 'subfolder/photo';
        self::assertSame('subfolder/photo.jpg', $file->getDisplayFilename());
    }

    public function testAPreviewOfAFileNoImageLibraryReadsIsItsAdminThumbnail(): void
    {
        $file = $this->insertDrawingWithPreview(fn (string $path) => $this->writeImage($path, 400, 300));
        $extension = File::getModule()->getPreviewExtension();

        self::assertSame(1, $file->transformation_count);

        $transformation = $file->transformations[0] ?? self::fail('No transformation was written.');

        self::assertSame(Transformation::NAME_ADMIN, $transformation->name);
        self::assertSame($extension, $transformation->extension);
        self::assertSame(120, $transformation->width);
        self::assertFileExists($transformation->getFilePath());
        self::assertStringEndsWith("/admin/$file->basename.$extension", (string)$file->getPreviewUrl());

        $this->replaceFile($file, 'drawing.svg', 20);

        self::assertSame(0, $file->transformation_count);
        self::assertNull($file->getPreviewUrl());
    }

    public function testAPreviewThatIsNoImageIsDropped(): void
    {
        $file = $this->insertDrawingWithPreview(static fn (string $path) => file_put_contents($path, 'not an image'));

        self::assertFalse($file->getIsNewRecord());
        self::assertSame(0, $file->transformation_count);
        self::assertNull($file->getPreviewUrl());
    }

    public function testDeletingAFileRemovesItsHashDirectory(): void
    {
        $file = $this->createFile('photo', 'jpg', 100, 100);
        $this->replaceFile($file, 'photo.jpg', 20);
        $directory = dirname($file->getFilePath());

        self::assertDirectoryExists($directory);

        $file->delete();

        self::assertDirectoryDoesNotExist($directory);
        self::assertDirectoryExists($this->folder->getUploadPath());
    }

    public function testADimensionBelowOneIsInvalid(): void
    {
        $file = $this->createFile('pic', 'jpg', 100, 100);
        $file->width = -5;

        self::assertFalse($file->validate());
        self::assertArrayHasKey('width', $file->getErrors());
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

    /**
     * @see https://github.com/davidhirtz/yii2-monorepo/issues/390
     */
    public function testAMoveWithAReplacementRecountsBothFolders(): void
    {
        $target = $this->createFolder('Target', 'target');
        $file = $this->createFile('photo', 'jpg');
        $before = Folder::findOne(1)?->file_count;

        $source = Yii::getAlias('@runtime/file-test/replacement.jpg');
        $this->writeImage($source, 20, 20);

        try {
            self::assertTrue($file->copy($source));
            $file->populateFolderRelation($target);

            self::assertSame(1, $file->update(), print_r($file->getErrors(), true));
        } finally {
            FileHelper::removeDirectory(dirname($source));
        }

        self::assertSame($before - 1, Folder::findOne(1)?->file_count);
        self::assertSame(1, Folder::findOne($target->id)?->file_count);
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

    private function replaceFile(File $file, string $name, int $width): void
    {
        $source = Yii::getAlias("@runtime/file-test/$name");
        $this->writeImage($source, $width, 20);

        try {
            self::assertTrue($file->copy($source));
            self::assertSame(1, $file->update(), print_r($file->getErrors(), true));
        } finally {
            FileHelper::removeDirectory(dirname($source));
        }
    }

    /**
     * @param Closure(string): mixed $writePreview
     */
    private function insertDrawingWithPreview(Closure $writePreview): File
    {
        $directory = Yii::getAlias('@runtime/file-test');
        FileHelper::createDirectory($directory);

        file_put_contents("$directory/drawing.svg", '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>');
        $writePreview("$directory/preview.jpg");

        try {
            $file = File::create();
            $file->loadDefaultValues();
            $file->populateFolderRelation($this->folder);

            self::assertTrue($file->copy("$directory/drawing.svg"));
            $file->preview = new CopiedUploadedFile(['path' => "$directory/preview.jpg"]);

            self::assertTrue($file->insert(), print_r($file->getErrors(), true));
        } finally {
            FileHelper::removeDirectory($directory);
        }

        return $file;
    }
}
