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
}
