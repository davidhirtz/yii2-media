<?php

declare(strict_types=1);

namespace Hirtz\Media\tests\unit\Models;

use Codeception\Test\Unit;
use Hirtz\Media\Models\Collections\FolderCollection;

class FolderTest extends Unit
{
    public function testFolder(): void
    {
        $folder = FolderCollection::getDefault();
        $this->assertFalse($folder->getIsNewRecord());
    }
}
