<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Console;

use Hirtz\Media\Console\Controllers\FileController;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\Models\TestAssetModel;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Test\Traits\StdOutBufferControllerTrait;
use Override;
use Yii;

/**
 * `file/clear` deletes every file no asset points at, so a file that is still in use has to survive it.
 */
class FileControllerTest extends TestCase
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
        ];
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = Folder::findOne(1);
        FileHelper::createDirectory($this->folder->getUploadPath());
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        parent::tearDown();
    }

    public function testOnlyTheFilesWithoutAnAssetAreDeleted(): void
    {
        $unused = $this->createFile('unused');
        $used = $this->createFile('used');

        $this->createAsset($used);

        $path = $unused->getFilePath();

        $controller = new TestFileController('file', Yii::$app);
        $controller->actionClear();

        self::assertStringContainsString('1 unused files were deleted', $controller->flushStdOutBuffer());

        self::assertNull(File::findOne($unused->id));
        self::assertFileDoesNotExist($path);

        self::assertNotNull(File::findOne($used->id));
        self::assertFileExists($used->getFilePath());
    }

    public function testTheFolderCountIsRecalculated(): void
    {
        $this->createFile('unused');

        self::assertSame(1, Folder::findOne(1)->file_count);

        $controller = new TestFileController('file', Yii::$app);
        $controller->actionClear();

        self::assertSame(0, Folder::findOne(1)->file_count);
    }

    private function createAsset(File $file): TestAsset
    {
        $asset = TestAsset::create();
        $asset->loadDefaultValues();
        $asset->model_class = TestAssetModel::class;
        $asset->model_id = 1;
        $asset->populateFileRelation($file);

        self::assertTrue($asset->insert(), print_r($asset->getErrors(), true));

        return $asset;
    }

    private function createFile(string $basename): File
    {
        $path = $this->folder->getUploadPath() . "$basename.jpg";

        $image = imagecreatetruecolor(100, 100);
        imagejpeg($image, $path);
        imagedestroy($image);

        $file = File::create();
        $file->loadDefaultValues();
        $file->name = ucfirst($basename);
        $file->basename = $basename;
        $file->extension = 'jpg';
        $file->width = 100;
        $file->height = 100;
        $file->size = filesize($path) ?: 0;
        $file->populateFolderRelation($this->folder);

        self::assertTrue($file->insert(), print_r($file->getErrors(), true));

        return $file;
    }
}

class TestFileController extends FileController
{
    use StdOutBufferControllerTrait;
}
