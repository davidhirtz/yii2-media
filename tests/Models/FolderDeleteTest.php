<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Models\Queries\FileQuery;
use Hirtz\Media\Test\Fixtures\FileFixture;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\Models\TestAssetModel;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Models\Redirect;
use Hirtz\Skeleton\Models\Search;
use Override;

/**
 * `file.folder_id` is a foreign key with `ON DELETE CASCADE`, so a folder used to take the file rows alone and
 * leave their assets, redirects and search documents behind.
 */
class FolderDeleteTest extends TestCase
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

        $this->folder = $this->createFolder('Archive', 'archive');
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        parent::tearDown();
    }

    public function testTheFilesAreDeletedWithTheFolder(): void
    {
        $file = $this->createFile('first');

        self::assertSame(1, $this->folder->delete());

        self::assertNull(Folder::findOne($this->folder->id));
        self::assertNull(File::findOne($file->id));
        self::assertDirectoryDoesNotExist($this->folder->getUploadPath());
    }

    public function testTheAssetsOfEveryFileAreDeleted(): void
    {
        $file = $this->createFile('first');
        $asset = $this->createAsset($file);

        self::assertSame(1, $this->folder->delete());
        self::assertNull(Asset::findOne($asset->id));
    }

    public function testTheSearchDocumentsOfEveryFileAreDeleted(): void
    {
        $file = $this->createFile('first');
        self::assertGreaterThan(0, $this->getSearchDocumentCount($file));

        self::assertSame(1, $this->folder->delete());
        self::assertSame(0, $this->getSearchDocumentCount($file));
    }

    public function testTheRedirectsToEveryFileAreDeleted(): void
    {
        $file = $this->createFile('first');

        $redirect = Redirect::create();
        $redirect->request_uri = 'archive/previous.jpg';
        $redirect->url = Url::sanitize($file->getUrl());

        self::assertTrue($redirect->insert(), print_r($redirect->getErrors(), true));

        self::assertSame(1, $this->folder->delete());
        self::assertNull(Redirect::findOne($redirect->id));
    }

    /**
     * The count of the folder being deleted is not written back — its row is on its way out — while every other
     * folder the files touched is.
     */
    public function testTheFolderBeingDeletedIsNotRecounted(): void
    {
        $this->createFile('first');

        self::assertSame(1, $this->folder->delete());
        self::assertSame(6, Folder::findOne(1)->file_count);
    }

    public function testANonEmptyFolderIsKeptWhileTheModuleSaysSo(): void
    {
        $file = $this->createFile('first');
        Folder::getModule()->enableDeleteNonEmptyFolders = false;

        self::assertFalse($this->folder->delete());
        self::assertArrayHasKey('file_count', $this->folder->getErrors());

        self::assertNotNull(Folder::findOne($this->folder->id));
        self::assertNotNull(File::findOne($file->id));
    }

    public function testAnEmptyFolderIsDeletedWhileTheModuleRefusesTheRest(): void
    {
        Folder::getModule()->enableDeleteNonEmptyFolders = false;

        self::assertSame(1, $this->folder->delete());
        self::assertNull(Folder::findOne($this->folder->id));
    }

    /**
     * A file that refuses to be deleted keeps the folder, rather than letting the cascade take it.
     */
    public function testAFailingFileKeepsTheFolder(): void
    {
        $folder = $this->createFolder('Locked', 'locked', UndeletableFilesFolder::class);
        $file = $this->createFile('first', $folder);

        self::assertFalse($folder->delete());
        self::assertArrayHasKey('file_count', $folder->getErrors());

        self::assertNotNull(Folder::findOne($folder->id));
        self::assertNotNull(File::findOne($file->id));
    }

    private function getSearchDocumentCount(File $file): int
    {
        return (int)Search::find()
            ->where([
                'model_class' => File::class,
                'model_id' => $file->id,
            ])
            ->count();
    }

    /**
     * @param class-string<Folder> $model
     */
    private function createFolder(string $name, string $path, string $model = Folder::class): Folder
    {
        $folder = $model::create();
        $folder->loadDefaultValues();
        $folder->name = $name;
        $folder->path = $path;

        self::assertTrue($folder->insert(), print_r($folder->getErrors(), true));

        return $folder;
    }

    private function createFile(string $basename, ?Folder $folder = null): File
    {
        $folder ??= $this->folder;
        $path = $folder->getUploadPath() . "$basename.jpg";
        $this->writeImage($path);

        $file = File::create();
        $file->loadDefaultValues();
        $file->name = ucfirst($basename);
        $file->basename = $basename;
        $file->extension = 'jpg';
        $file->width = 100;
        $file->height = 100;
        $file->size = filesize($path) ?: 0;
        $file->populateFolderRelation($folder);

        self::assertTrue($file->insert(), print_r($file->getErrors(), true));

        return $file;
    }

    private function createAsset(File $file): TestAsset
    {
        $model = TestAssetModel::create();
        $model->id = 1;

        $asset = TestAsset::create();
        $asset->populateFileRelation($file);
        $asset->populateModelRelation($model);

        self::assertTrue($asset->insert(), print_r($asset->getErrors(), true));

        return $asset;
    }

    private function writeImage(string $path): void
    {
        FileHelper::createDirectory(dirname($path));

        $image = imagecreatetruecolor(100, 100);
        imagejpeg($image, $path);
    }
}

class UndeletableFolderFile extends File
{
    #[Override]
    public function beforeDelete(): bool
    {
        return false;
    }
}

/**
 * The folder reloads its files, so the refusal has to come from the class the relation returns.
 */
class UndeletableFilesFolder extends Folder
{
    #[Override]
    public function getFiles(): FileQuery
    {
        /** @var FileQuery $relation */
        $relation = $this->hasMany(UndeletableFolderFile::class, ['folder_id' => 'id'])
            ->indexBy('id')
            ->inverseOf('folder');

        return $relation;
    }
}
