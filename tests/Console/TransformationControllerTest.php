<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Console;

use Hirtz\Media\Console\Controllers\TransformationController;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Models\FileTransformation;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Media\Test\Fixtures\FileFixture;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Test\Traits\StdOutBufferControllerTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Override;
use Yii;

/**
 * `transformation/delete` is how a renamed or dropped transformation is cleared out, so it runs against names the
 * module no longer configures.
 */
class TransformationControllerTest extends TestCase
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

        File::getModule()->setTransformations([
            Transformation::make('square')->width(50)->height(50),
            Transformation::make('legacy')->width(60)->height(60),
        ]);

        $this->folder = Folder::findOne(1);
        FileHelper::createDirectory($this->folder->getUploadPath());
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        parent::tearDown();
    }

    public function testIndexCountsTheTransformationsPerName(): void
    {
        $file = $this->createFile('photo');
        $this->createTransformation($file, 'legacy');

        $controller = $this->createController();
        $controller->actionIndex();

        $output = $controller->flushStdOutBuffer();

        self::assertStringContainsString('legacy  (1)', $output);
        self::assertStringContainsString('square  (0)', $output);
    }

    /**
     * The command exists to find these, so a name the module dropped has to be listed beside the configured ones.
     */
    public function testIndexListsANameTheModuleNoLongerConfigures(): void
    {
        $file = $this->createFile('photo');
        $this->createTransformation($file, 'legacy');

        File::getModule()->removeTransformation('legacy');

        $controller = $this->createController();
        $controller->actionIndex();

        self::assertStringContainsString('legacy  (1)', $controller->flushStdOutBuffer());
    }

    public function testDeleteRemovesTheRecordsTheFilesAndTheDirectories(): void
    {
        $other = $this->createFolder('Archive', 'archive');

        $first = $this->createFile('photo');
        $second = $this->createFile('drawing', folder: $other);

        $this->createTransformation($first, 'legacy');
        $this->createTransformation($second, 'legacy');
        $kept = $this->createTransformation($first, 'square');

        $paths = [
            $this->folder->getUploadPath() . 'legacy',
            $other->getUploadPath() . 'legacy',
        ];

        File::getModule()->removeTransformation('legacy');

        $controller = $this->createController();
        $controller->actionDelete('legacy');

        self::assertSame(0, (int)FileTransformation::find()->where(['name' => 'legacy'])->count());
        self::assertNotNull(FileTransformation::findOne($kept->id));

        foreach ($paths as $path) {
            self::assertDirectoryDoesNotExist($path);
        }

        self::assertDirectoryExists($this->folder->getUploadPath() . 'square');
    }

    public function testDeleteRecalculatesTheFileTransformationCount(): void
    {
        $file = $this->createFile('photo');

        $this->createTransformation($file, 'legacy');
        $this->createTransformation($file, 'square');

        self::assertSame(2, File::findOne($file->id)->transformation_count);

        File::getModule()->removeTransformation('legacy');

        $controller = $this->createController();
        $controller->actionDelete('legacy');

        self::assertSame(1, File::findOne($file->id)->transformation_count);
    }

    /**
     * A mistyped name would otherwise report the same success as a real one, and the outdated files would silently
     * stay on disk.
     */
    public function testANameThatMatchesNothingIsReported(): void
    {
        $this->createTransformation($this->createFile('photo'), 'legacy');

        $controller = $this->createController();
        $controller->actionDelete('lgeacy');

        $output = $controller->flushStdOutBuffer();

        self::assertStringContainsString('Nothing found', $output);
        self::assertSame(1, (int)FileTransformation::find()->where(['name' => 'legacy'])->count());
    }

    /**
     * Records deleted without their directories, or the other way round, are exactly what this is run to clean up.
     */
    public function testADirectoryWithoutRecordsIsStillRemoved(): void
    {
        $path = $this->folder->getUploadPath() . 'legacy';
        FileHelper::createDirectory($path);

        $controller = $this->createController();
        $controller->actionDelete('legacy');

        self::assertDirectoryDoesNotExist($path);
        self::assertStringContainsString('(0 files, 1 folders)', $controller->flushStdOutBuffer());
    }

    /**
     * A name is refused rather than sanitized: rewriting it would delete a different transformation than the one
     * that was asked for.
     */
    #[DataProvider('invalidNameDataProvider')]
    public function testANameCannotReachOutOfTheUploadDirectory(string $name): void
    {
        $this->createTransformation($this->createFile('photo'), 'legacy');

        $controller = $this->createController();
        $controller->actionDelete($name);

        self::assertStringContainsString('Invalid transformation name', $controller->flushStdOutBuffer());
        self::assertSame(1, (int)FileTransformation::find()->where(['name' => 'legacy'])->count());

        self::assertDirectoryExists($this->folder->getUploadPath());
        self::assertDirectoryExists((string)File::getModule()->uploadPath);
    }

    /**
     * @return list<array{string}>
     */
    public static function invalidNameDataProvider(): array
    {
        return [
            [''],
            ['.'],
            ['..'],
            ['../legacy'],
            ['../../legacy'],
            ['legacy/../../..'],
            ['default/legacy'],
        ];
    }

    private function createController(): TestTransformationController
    {
        return new TestTransformationController('transformation', Yii::$app);
    }

    private function createTransformation(File $file, string $name): FileTransformation
    {
        $transformation = FileTransformation::create();
        $transformation->name = $name;
        $transformation->populateFileRelation($file);

        self::assertTrue($transformation->insert(), print_r($transformation->getErrors(), true));

        return $transformation;
    }
}

class TestTransformationController extends TransformationController
{
    use StdOutBufferControllerTrait;
}
