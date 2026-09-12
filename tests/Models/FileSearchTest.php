<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models;

use Hirtz\Media\Models\File;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;

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

    public function testTheGridFindsAFileByItsFilename(): void
    {
        $files = File::find()->matching('test-1.jpg')->all();
        self::assertSame([1], array_map(fn (File $file): int => $file->id, $files));

        $files = File::find()->matching('test-2')->all();
        self::assertSame([2], array_map(fn (File $file): int => $file->id, $files));

        self::assertEmpty(File::find()->matching('test-1.svg')->all());
    }
}
