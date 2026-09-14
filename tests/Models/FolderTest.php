<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models;

use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Test\TestCase;

class FolderTest extends TestCase
{
    public function testFolder(): void
    {
        $folder = FolderCollection::getDefault();
        self::assertFalse($folder->getIsNewRecord());
    }

    /**
     * The records a request loaded must never reach the next one, so `Bootstrap` drops them — a reset that only ran
     * in the tests would leave a resident application serving them forever.
     */
    public function testTheFoldersDoNotOutliveTheApplication(): void
    {
        $folder = FolderCollection::getDefault();
        self::assertSame($folder, FolderCollection::getDefault());

        $this->reloadApplication();

        self::assertNotSame($folder, FolderCollection::getDefault());
    }
}
