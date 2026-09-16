<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models\Actions;

use Hirtz\Media\Models\Actions\DeleteFiles;
use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\Fixtures\FileFixture;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\Models\TestAssetModel;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Models\Search;
use Override;
use Yii;

class DeleteFilesTest extends TestCase
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

        $this->folder = Folder::findOne(1);
        FileHelper::createDirectory($this->folder->getUploadPath());
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        parent::tearDown();
    }

    public function testTheFilesAreDeletedFromTheDatabaseAndTheDisk(): void
    {
        $first = $this->createFile('first');
        $second = $this->createFile('second');
        $kept = $this->createFile('kept');

        $action = DeleteFiles::create([$first, $second]);

        self::assertCount(2, $action->getDeleted());
        self::assertSame([], $action->getFailed());

        self::assertNull(File::findOne($first->id));
        self::assertNull(File::findOne($second->id));
        self::assertNotNull(File::findOne($kept->id));

        self::assertFileDoesNotExist($this->folder->getUploadPath() . 'first.jpg');
        self::assertFileExists($this->folder->getUploadPath() . 'kept.jpg');
    }

    /**
     * `File::afterDelete()` recalculates the folder on every single delete, and each is a `COUNT(*)` — a batch
     * pays it once for the whole selection.
     */
    public function testTheFileCountIsRecalculatedOnce(): void
    {
        $files = [$this->createFile('first'), $this->createFile('second'), $this->createFile('third')];
        $before = $this->getUpdateCount();

        DeleteFiles::create($files);

        self::assertSame(1, $this->getUpdateCount() - $before);
        self::assertSame(6, Folder::findOne($this->folder->id)->file_count);
    }

    public function testEveryFolderTheSelectionSpansIsRecounted(): void
    {
        $other = $this->createFolder('Archive', 'archive');

        $first = $this->createFile('first');
        $second = $this->createFile('second', folder: $other);

        DeleteFiles::create([$first, $second]);

        self::assertSame(6, Folder::findOne($this->folder->id)->file_count);
        self::assertSame(0, Folder::findOne($other->id)->file_count);
    }

    /**
     * A file that refuses to be deleted is reported and the ones beside it are kept.
     */
    public function testAFailingFileIsReported(): void
    {
        $deleted = $this->createFile('first');
        $failing = $this->createFile('failing', model: UndeletableFile::class);

        $action = DeleteFiles::create([$deleted, $failing]);

        self::assertCount(1, $action->getDeleted());
        self::assertCount(1, $action->getFailed());

        self::assertNull(File::findOne($deleted->id));
        self::assertNotNull(File::findOne($failing->id));
    }

    /**
     * The assets are deleted through their own models, so the record each hangs on is recounted and its trail
     * written — the foreign key's cascade would take the rows and nothing else.
     */
    public function testTheAssetsGoWithTheFile(): void
    {
        $file = $this->createFile('first');
        $asset = $this->createAsset($file);

        DeleteFiles::create([$file]);

        self::assertNull(Asset::findOne($asset->id));
    }

    public function testTheSearchDocumentsGoWithTheFile(): void
    {
        $file = $this->createFile('first');
        self::assertGreaterThan(0, $this->getSearchDocumentCount($file));

        DeleteFiles::create([$file]);

        self::assertSame(0, $this->getSearchDocumentCount($file));
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
     * `SHOW SESSION STATUS` is itself a query, but it is not an update, so the counter it reads is unaffected.
     */
    private function getUpdateCount(): int
    {
        $row = Yii::$app->getDb()
            ->createCommand("SHOW SESSION STATUS LIKE 'Com_update'")
            ->queryOne();

        return (int)($row['Value'] ?? 0);
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
}

class UndeletableFile extends File
{
    #[Override]
    public function beforeDelete(): bool
    {
        return false;
    }
}
