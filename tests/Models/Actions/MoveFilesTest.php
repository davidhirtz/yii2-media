<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models\Actions;

use Hirtz\Media\Models\Actions\MoveFiles;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\Fixtures\FileFixture;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Models\Trail;
use Override;
use Yii;

class MoveFilesTest extends TestCase
{
    private Folder $folder;
    private Folder $target;

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
        $this->target = $this->createFolder('Archive', 'archive');

        FileHelper::createDirectory($this->folder->getUploadPath());
        FileHelper::createDirectory($this->target->getUploadPath());
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        parent::tearDown();
    }

    public function testTheFilesAreMovedOnDiskAndInTheDatabase(): void
    {
        $first = $this->createFile('first');
        $second = $this->createFile('second');

        $action = MoveFiles::create([$first, $second], $this->target);

        self::assertSame(2, $action->getMovedCount());
        self::assertEmpty($action->getFailed());

        foreach ([$first, $second] as $file) {
            $file->refresh();

            self::assertSame($this->target->id, $file->folder_id);
            self::assertFileExists($this->target->getUploadPath() . $file->getFilename());
            self::assertFileDoesNotExist($this->folder->getUploadPath() . $file->getFilename());
        }
    }

    /**
     * The count of both folders is recalculated once, after the last file was moved, rather than twice per file.
     */
    public function testTheFileCountsAreRecalculated(): void
    {
        $files = [$this->createFile('first'), $this->createFile('second')];
        $before = Folder::findOne($this->folder->id)->file_count;

        MoveFiles::create($files, $this->target);

        self::assertSame($before - 2, Folder::findOne($this->folder->id)->file_count);
        self::assertSame(2, Folder::findOne($this->target->id)->file_count);
    }

    /**
     * `File::afterSave()` recalculates the previous and the new folder on every single save, and each is a
     * `COUNT(*)` over the folder. A batch pays that twice in total rather than twice per file: three files are
     * three file rows plus the two folders.
     */
    public function testTheFileCountsAreNotRecalculatedPerFile(): void
    {
        $files = [$this->createFile('first'), $this->createFile('second'), $this->createFile('third')];
        $before = $this->getUpdateCount();

        MoveFiles::create($files, $this->target);

        self::assertSame(5, $this->getUpdateCount() - $before);
    }

    public function testAFileAlreadyInTheTargetFolderIsSkipped(): void
    {
        $file = $this->createFile('first');
        $action = MoveFiles::create([$file], $this->folder);

        self::assertSame(0, $action->getMovedCount());
        self::assertEmpty($action->getFailed());
        self::assertSame($this->folder->id, File::findOne($file->id)->folder_id);
    }

    /**
     * A name that is taken in the target folder is renamed rather than refused, so the caller can say so. A
     * transformable image collides through the database, since its transformations share one name.
     */
    public function testACollidingNameIsRenamedAndReported(): void
    {
        $file = $this->createFile('photo');
        $this->createFile('photo', $this->target);

        $action = MoveFiles::create([$file], $this->target);

        self::assertSame(1, $action->getMovedCount());
        self::assertCount(1, $action->getRenamed());

        $file->refresh();

        self::assertSame('photo_1', $file->basename);
        self::assertFileExists($this->target->getUploadPath() . 'photo_1.jpg');
    }

    public function testTheMoveIsRecordedInTheTrail(): void
    {
        $file = $this->createFile('first');
        $count = $this->getTrailCount();

        MoveFiles::create([$file], $this->target);

        self::assertSame($count + 1, $this->getTrailCount());
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

    private function getTrailCount(): int
    {
        return (int)Trail::find()
            ->andWhere(['model_class' => File::class])
            ->count();
    }

    private function createFolder(string $name, string $path): Folder
    {
        $folder = Folder::create();
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

        if (!is_file($file->getFilePath())) {
            $this->writeImage($file->getFilePath());
        }

        return $file;
    }

    private function writeImage(string $path): void
    {
        FileHelper::createDirectory(dirname($path));

        $image = imagecreatetruecolor(100, 100);
        imagejpeg($image, $path);
    }
}
