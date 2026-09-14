<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Skeleton\Models\Search;

/**
 * The filename is what an editor searches for, and neither `basename` nor the grid's `LIKE` used to hold it.
 */
class FileSearchTest extends TestCase
{
    use MediaFixtureTrait;

    public function testTheDocumentCarriesTheFilename(): void
    {
        $file = $this->getFileFromFixture('file-1');
        $document = $file->getSearchDocuments()[0];

        self::assertSame('Test 1', $document->title);
        self::assertStringContainsString('test-1.jpg', $document->content);
        self::assertStringContainsString('Alt Text 1', $document->content);
    }

    /**
     * `filename` is a getter, so nothing in `changedAttributes` is named after it — the index used to keep the old
     * name until the next `search/rebuild`.
     */
    public function testARenameRewritesTheDocument(): void
    {
        $file = $this->indexFile('file-1');
        $document = $this->findDocument($file);

        self::assertStringContainsString('test-1.jpg', $document->content);

        $file->basename = 'renamed';
        self::assertSame(1, $file->update());

        $document = $this->findDocument($file);

        self::assertStringContainsString('renamed.jpg', $document->content);
        self::assertStringNotContainsString('test-1.jpg', $document->content);
    }

    public function testAFolderChangeDoesNotRewriteTheDocument(): void
    {
        $file = $this->indexFile('file-1');
        $id = $this->findDocument($file)->id;

        $file->folder_id = $this->createFolder()->id;
        self::assertSame(1, $file->update());

        self::assertSame($id, $this->findDocument($file)->id);
    }

    public function testTheGridFindsAFileByItsFilename(): void
    {
        $files = File::find()->matching('test-1.jpg')->all();
        self::assertSame([1], array_map(fn (File $file): int => $file->id, $files));

        $files = File::find()->matching('test-2')->all();
        self::assertSame([2], array_map(fn (File $file): int => $file->id, $files));

        self::assertEmpty(File::find()->matching('test-1.svg')->all());
    }

    /**
     * A fixture is inserted without `save()`, so nothing indexed it: one save of a searchable attribute writes the
     * documents the rename is then measured against.
     */
    private function indexFile(string $key): File
    {
        $file = $this->getFileFromFixture($key);
        $file->alt_text = 'Indexed';

        self::assertSame(1, $file->update());

        return $file;
    }

    private function findDocument(File $file): Search
    {
        $document = Search::find()
            ->where([
                'model_class' => File::class,
                'model_id' => $file->id,
            ])
            ->orderBy(['language' => SORT_ASC])
            ->one();

        self::assertInstanceOf(Search::class, $document);

        return $document;
    }

    private function createFolder(): Folder
    {
        $folder = Folder::create();
        $folder->loadDefaultValues();
        $folder->name = 'Archive';
        $folder->path = 'archive';

        self::assertTrue($folder->insert(), print_r($folder->getErrors(), true));

        return $folder;
    }
}
