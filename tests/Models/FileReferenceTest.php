<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models;

use Hirtz\Media\Console\Controllers\FileController;
use Hirtz\Media\Models\CustomAttributes\FileCustomAttribute;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\FileReference;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\Models\FileAttributeRecord;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\FileAttributeRecordTrait;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Skeleton\Db\DateTime;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Models\Actions\DuplicateActiveRecord;
use Hirtz\Skeleton\Models\CustomAttributes\GroupCustomAttribute;
use Hirtz\Skeleton\Models\CustomAttributes\TextCustomAttribute;
use Hirtz\Skeleton\Models\Trail;
use Hirtz\Skeleton\Test\Traits\StdOutBufferControllerTrait;
use Override;
use Yii;

class FileReferenceTest extends TestCase
{
    use FileAttributeRecordTrait;
    use MediaFileTrait;

    private File $a;
    private File $b;

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

        Yii::$app->getI18n()->setLanguages(['en-US', 'de']);

        Yii::$container->set(FileAttributeRecord::class, [
            'customAttributes' => [
                FileCustomAttribute::make('hero')
                    ->translatable(),
                FileCustomAttribute::make('icon')
                    ->required(),
                GroupCustomAttribute::make('gallery')
                    ->multiple()
                    ->attributes([
                        FileCustomAttribute::make('image'),
                        TextCustomAttribute::make('caption'),
                    ]),
                GroupCustomAttribute::make('slides')
                    ->multiple()
                    ->attributes([
                        TextCustomAttribute::make('title'),
                        GroupCustomAttribute::make('items')
                            ->multiple()
                            ->attributes([FileCustomAttribute::make('image')]),
                    ]),
            ],
        ]);

        $this->folder = Folder::findOne(1) ?? self::fail('The folder fixture was not loaded.');
        FileHelper::createDirectory($this->folder->getUploadPath());

        $this->a = $this->createFile('alpha');
        $this->a->updateAttributes(['name' => 'Å Москва']);

        $this->b = $this->createFile('beta');
        $this->b->updateAttributes(['name' => '東京 🦸']);
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        Yii::$container->clear(FileAttributeRecord::class);

        parent::tearDown();
    }

    #[Override]
    protected function setUpSchema(): void
    {
        $this->createFileAttributeRecordTable();
    }

    #[Override]
    protected function tearDownSchema(): void
    {
        $this->dropFileAttributeRecordTable();
    }

    public function testASaveRecordsARowPerFileWithItsCounts(): void
    {
        $record = $this->createFileAttributeRecord([
            'hero' => $this->a->id,
            'hero_de' => $this->b->id,
            'icon' => $this->a->id,
            'gallery' => [['image' => $this->a->id, 'caption' => 'Mu' . "\u{308}" . 'ller'], ['image' => $this->b->id], ['image' => $this->a->id]],
            'slides' => [['title' => 'Ą', 'items' => [['image' => $this->b->id]]]],
        ]);

        self::assertSame([
            $this->a->id => [4, ['gallery.image' => 2, 'hero' => 1, 'icon' => 1]],
            $this->b->id => [3, ['gallery.image' => 1, 'hero_de' => 1, 'slides.items.image' => 1]],
        ], $this->findReferences($record));

        self::assertSame(4, $this->refresh($this->a)->reference_count);
        self::assertSame(3, $this->refresh($this->b)->reference_count);
        self::assertSame(0, $this->refresh($this->a)->asset_count);
    }

    public function testAReorderWritesNoReference(): void
    {
        $record = $this->createFileAttributeRecord([
            'icon' => $this->a->id,
            'gallery' => [['image' => $this->a->id], ['image' => $this->b->id]],
        ]);

        $record = $this->findFileAttributeRecord($record->id);
        $record->gallery = array_reverse((array)$record->gallery);

        $writes = $this->countQueries(
            fn () => self::assertTrue($record->save()),
            '/^(INSERT|UPDATE|DELETE).*`file(_reference)?`/',
        );

        self::assertSame(0, $writes);
    }

    public function testRemovingAUseLowersTheCountAndTheLastOneDeletesTheRow(): void
    {
        $record = $this->createFileAttributeRecord([
            'icon' => $this->b->id,
            'gallery' => [['image' => $this->a->id], ['image' => $this->a->id]],
        ]);

        $record = $this->findFileAttributeRecord($record->id);
        $record->gallery = [['image' => $this->a->id]];
        self::assertTrue($record->save());

        self::assertSame([$this->a->id => [1, ['gallery.image' => 1]], $this->b->id => [1, ['icon' => 1]]], $this->findReferences($record));
        self::assertSame(1, $this->refresh($this->a)->reference_count);

        $record->gallery = null;
        self::assertTrue($record->save());

        self::assertSame([$this->b->id => [1, ['icon' => 1]]], $this->findReferences($record));
        self::assertSame(0, $this->refresh($this->a)->reference_count);
    }

    public function testDeletingTheOwnerDeletesItsRows(): void
    {
        $record = $this->createFileAttributeRecord(['icon' => $this->a->id, 'hero' => $this->a->id]);

        self::assertSame(1, $record->delete());

        self::assertSame([], $this->findReferences($record));
        self::assertSame(0, $this->refresh($this->a)->reference_count);
    }

    public function testADuplicateGetsRowsOfItsOwn(): void
    {
        $record = $this->createFileAttributeRecord(['icon' => $this->a->id]);
        $duplicate = DuplicateActiveRecord::create(['model' => $this->findFileAttributeRecord($record->id)]);
        self::assertInstanceOf(FileAttributeRecord::class, $duplicate);

        self::assertSame([$this->a->id => [1, ['icon' => 1]]], $this->findReferences($duplicate));
        self::assertSame(2, $this->refresh($this->a)->reference_count);
    }

    public function testDeletingAFileClearsEveryUseAndWritesTheTrail(): void
    {
        $record = $this->createFileAttributeRecord([
            'hero' => $this->b->id,
            'hero_de' => $this->a->id,
            'icon' => $this->a->id,
            'gallery' => [['image' => $this->a->id, 'caption' => 'Москва'], ['image' => $this->b->id]],
        ]);

        $record->updateAttributes(['updated_at' => new DateTime('2020-01-01 00:00:00')]);

        self::assertSame(1, $this->a->delete());

        $record = $this->findFileAttributeRecord($record->id);

        self::assertSame($this->b->id, $record->hero);
        self::assertNull($record->hero_de);
        self::assertNull($record->icon, 'a required use is cleared too');
        self::assertSame([['caption' => 'Москва'], ['image' => $this->b->id]], $record->gallery);
        self::assertGreaterThan(new DateTime('2020-01-02 00:00:00'), $record->updated_at);
        self::assertSame([$this->b->id], array_keys($this->findReferences($record)));
        self::assertFalse(FileReference::find()->where(['file_id' => $this->a->id])->exists());

        $trail = Trail::find()
            ->where(['model_class' => FileAttributeRecord::class, 'model_id' => (string)$record->id, 'type' => Trail::TYPE_ORDER])
            ->one();

        self::assertNotNull($trail);
        self::assertStringContainsString($this->a->getFilename(), (string)$trail->message);
    }

    public function testDeletingAFolderClearsTheUsesOfItsFiles(): void
    {
        $folder = $this->createFolder('Ą Archive', 'archive');
        $file = $this->createFile('archived', folder: $folder);
        $record = $this->createFileAttributeRecord(['icon' => $file->id]);

        self::assertSame(1, $folder->delete(), print_r($folder->getErrors(), true));

        self::assertNull($this->findFileAttributeRecord($record->id)->icon);
        self::assertSame([], $this->findReferences($record));
    }

    public function testDeleteUnusedKeepsAReferencedFile(): void
    {
        $this->createFileAttributeRecord(['icon' => $this->a->id]);

        $controller = new FileReferenceTestFileController('file', Yii::$app);
        $controller->interactive = false;
        $controller->actionDeleteUnused();

        self::assertNotNull(File::findOne($this->a->id));
        self::assertNull(File::findOne($this->b->id));
    }

    public function testTheRebuildRestoresMissingRowsAndRemovesOrphans(): void
    {
        File::getModule()->fileReferenceModels = [FileAttributeRecord::class];

        $record = $this->createFileAttributeRecord(['icon' => $this->a->id]);
        $gone = $this->createFileAttributeRecord(['icon' => $this->b->id]);

        FileReference::deleteAll(['model_id' => $record->id]);
        FileAttributeRecord::deleteAll(['id' => $gone->id]);

        $controller = new FileReferenceTestFileController('file', Yii::$app);
        $controller->actionReferences();

        self::assertStringContainsString('1 references of deleted records were removed', $controller->flushStdOutBuffer());
        self::assertSame([$this->a->id => [1, ['icon' => 1]]], $this->findReferences($record));
        self::assertSame([], $this->findReferences($gone));
        self::assertSame(1, $this->refresh($this->a)->reference_count);
        self::assertSame(0, $this->refresh($this->b)->reference_count);
    }

    /**
     * @return array<int, array{int, array<string, int>}>
     */
    private function findReferences(FileAttributeRecord $record): array
    {
        $references = FileReference::find()
            ->where(['model_class' => FileAttributeRecord::class, 'model_id' => $record->id])
            ->orderBy(['file_id' => SORT_ASC])
            ->all();

        $rows = [];

        foreach ($references as $reference) {
            $counts = $reference->attribute_counts ?? [];
            ksort($counts);
            $rows[$reference->file_id] = [$reference->use_count, $counts];
        }

        return $rows;
    }

    private function refresh(File $file): File
    {
        return File::findOne($file->id) ?? self::fail('The file was not found.');
    }
}

class FileReferenceTestFileController extends FileController
{
    use StdOutBufferControllerTrait;
}
