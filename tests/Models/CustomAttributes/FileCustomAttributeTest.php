<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models\CustomAttributes;

use Hirtz\Media\Models\Actions\LoadCustomAttributeFiles;
use Hirtz\Media\Models\CustomAttributes\FileCustomAttribute;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\Models\FileAttributeRecord;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Skeleton\Models\Actions\DuplicateActiveRecord;
use Hirtz\Skeleton\Models\CustomAttributes\GroupCustomAttribute;
use Hirtz\Skeleton\Models\CustomAttributes\TextCustomAttribute;
use Hirtz\Skeleton\Models\Types\Type;
use Override;
use Yii;
use yii\base\InvalidConfigException;

class FileCustomAttributeTest extends TestCase
{
    use MediaFixtureTrait;

    private const int TYPE_HERO = 2;

    private Folder $heroes;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Yii::$app->getI18n()->setLanguages(['en-US', 'de']);

        Yii::$container->set(Folder::class, [
            'types' => fn (): array => [
                Type::make(Folder::TYPE_DEFAULT)->name('Default'),
                Type::make(self::TYPE_HERO)->name('Heroes'),
            ],
        ]);

        $this->heroes = Folder::instantiateByType(self::TYPE_HERO);
        $this->heroes->type = self::TYPE_HERO;
        $this->heroes->name = 'Héroes Å 英雄';
        $this->heroes->path = 'heroes';
        self::assertTrue($this->heroes->insert());

        // file-2 is the one SVG, file-3 a JPEG, both moved into the hero folder
        foreach (['file-2', 'file-3'] as $key) {
            $this->getFileFromFixture($key)->updateAttributes(['folder_id' => $this->heroes->id]);
        }

        Yii::$container->set(FileAttributeRecord::class, [
            'customAttributes' => [
                FileCustomAttribute::make('hero')
                    ->folderType(self::TYPE_HERO)
                    ->translatable(),
                FileCustomAttribute::make('icon')
                    ->folders($this->heroes->id)
                    ->extensions('SVG'),
                GroupCustomAttribute::make('gallery')
                    ->multiple()
                    ->attributes([
                        FileCustomAttribute::make('image')
                            ->folderType(self::TYPE_HERO),
                        TextCustomAttribute::make('caption'),
                    ]),
            ],
        ]);
    }

    #[Override]
    protected function tearDown(): void
    {
        Yii::$container->clear(FileAttributeRecord::class);
        Yii::$container->clear(Folder::class);
        Yii::$container->clear(File::class);

        parent::tearDown();
    }

    #[Override]
    protected function setUpSchema(): void
    {
        Yii::$app->getDb()->createCommand()
            ->createTable(FileAttributeRecord::tableName(), [
                'id' => 'pk',
                'name' => 'string null',
                'custom_attributes' => 'json null',
            ])
            ->execute();
    }

    #[Override]
    protected function tearDownSchema(): void
    {
        Yii::$app->getDb()->createCommand()
            ->dropTable(FileAttributeRecord::tableName())
            ->execute();
    }

    public function testTheScopeIsTheFoldersOfTheTypeAndTheExtensions(): void
    {
        $record = $this->createRecord();
        $record->hero = $this->getFileFromFixture('file-3')->id;
        $record->icon = $this->getFileFromFixture('file-2')->id;

        self::assertTrue($record->validate(), implode(' ', $record->getErrorSummary(true)));

        $record->hero = $this->getFileFromFixture('file-1')->id;
        $record->icon = $this->getFileFromFixture('file-3')->id;

        self::assertFalse($record->validate());
        self::assertTrue($record->hasErrors('hero'));
        self::assertTrue($record->hasErrors('icon'));
    }

    public function testAStoredFileOutsideTheScopeBlocksNoUnrelatedSave(): void
    {
        $outside = $this->getFileFromFixture('file-1')->id;
        $inside = $this->getFileFromFixture('file-3')->id;

        $record = $this->createRecord();
        $record->updateAttributes(['custom_attributes' => [
            'hero' => $outside,
            'gallery' => [['image' => $outside, 'caption' => 'Москва'], ['image' => $inside]],
        ]]);

        $record = $this->findRecord($record->id);
        $record->name = '東京 🦸';
        $record->gallery = array_reverse((array)$record->gallery);

        self::assertTrue($record->save(), implode(' ', $record->getErrorSummary(true)));
    }

    public function testAChangedFileOutsideTheScopeFails(): void
    {
        $record = $this->createRecord();
        $record->gallery = [['image' => $this->getFileFromFixture('file-1')->id]];

        self::assertFalse($record->save());
        self::assertTrue($record->hasErrors('gallery.0.image'));
    }

    public function testADuplicateKeepsAFileThatLeftTheScope(): void
    {
        $outside = $this->getFileFromFixture('file-1')->id;

        $record = $this->createRecord();
        $record->updateAttributes(['custom_attributes' => ['hero' => $outside, 'gallery' => [['image' => $outside]]]]);

        $duplicate = DuplicateActiveRecord::create(['model' => $this->findRecord($record->id)]);
        self::assertInstanceOf(FileAttributeRecord::class, $duplicate);

        self::assertFalse($duplicate->getIsNewRecord(), implode(' ', $duplicate->getErrorSummary(true)));
        self::assertSame($outside, $duplicate->hero);
    }

    public function testATranslatedFileFallsBackToTheSourceLanguage(): void
    {
        $en = $this->getFileFromFixture('file-3');
        $de = $this->getFileFromFixture('file-2');

        $record = $this->createRecord(['hero' => $en->id]);

        self::assertSame($en->id, FileCustomAttribute::getFile($record, 'hero', 'de')?->id);

        $record->hero_de = $de->id;

        self::assertSame($de->id, FileCustomAttribute::getFile($record, 'hero', 'de')?->id);
        self::assertSame($en->id, FileCustomAttribute::getFile($record, 'hero')?->id);
        self::assertSame($this->heroes->id, FileCustomAttribute::getFile($record, 'hero')?->folder?->id);
    }

    public function testTheTrailValueIsTheFilename(): void
    {
        $file = $this->getFileFromFixture('file-3');
        $record = $this->createRecord();

        self::assertSame($file->getFilename(), $record->getCustomAttribute('hero')?->formatValue($record, $file->id));
    }

    public function testAFileCannotOwnTheAttribute(): void
    {
        Yii::$container->set(File::class, [
            'customAttributes' => [FileCustomAttribute::make('poster')],
        ]);

        $this->expectException(InvalidConfigException::class);

        File::create()->attributes();
    }

    public function testAFileCannotOwnTheAttributeInsideAGroup(): void
    {
        Yii::$container->set(File::class, [
            'customAttributes' => [
                GroupCustomAttribute::make('tracks')
                    ->attributes([FileCustomAttribute::make('poster')]),
            ],
        ]);

        $file = File::create();
        $file->setAttribute('tracks', ['poster' => 1]);

        $this->expectException(InvalidConfigException::class);

        $file->getCustomAttributeRecordIds(FileCustomAttribute::class);
    }

    public function testTheFilesOfSeveralRecordsAreLoadedInOneQuery(): void
    {
        $a = $this->getFileFromFixture('file-2');
        $b = $this->getFileFromFixture('file-3');

        $first = $this->findRecord($this->createRecord(['hero' => $a->id, 'gallery' => [['image' => $b->id]]])->id);
        $second = $this->findRecord($this->createRecord(['hero' => $b->id])->id);

        $queries = $this->countQueries(fn () => LoadCustomAttributeFiles::create([$first, $second]));

        self::assertSame(2, $queries, 'the files and their translations');

        $queries = $this->countQueries(function () use ($first, $second, $a, $b): void {
            self::assertSame($a->id, FileCustomAttribute::getFile($first, 'hero')?->id);
            self::assertSame($b->id, FileCustomAttribute::getFile($first->getCustomAttributeItems('gallery')[0], 'image')?->id);
            self::assertSame($b->id, FileCustomAttribute::getFile($second, 'hero')?->id);
        });

        self::assertSame(0, $queries);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createRecord(array $values = []): FileAttributeRecord
    {
        $record = FileAttributeRecord::create();
        $record->name = 'Å';
        $record->setAttributes($values, false);

        self::assertTrue($record->insert(), implode(' ', $record->getErrorSummary(true)));

        return $record;
    }

    private function findRecord(int $id): FileAttributeRecord
    {
        return FileAttributeRecord::find()->where(['id' => $id])->one() ?? self::fail('The record was not found.');
    }
}
