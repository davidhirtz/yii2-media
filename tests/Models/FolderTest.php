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
     * A reset that only ran in the tests would leave a resident application serving one request's folders forever.
     */
    public function testTheFoldersDoNotOutliveTheApplication(): void
    {
        $folder = FolderCollection::getDefault();
        self::assertSame($folder, FolderCollection::getDefault());

        $this->reloadApplication();

        self::assertNotSame($folder, FolderCollection::getDefault());
    }
}
