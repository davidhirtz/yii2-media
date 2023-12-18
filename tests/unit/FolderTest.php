<?php

namespace davidhirtz\yii2\media\tests\unit;

use Codeception\Test\Unit;
use davidhirtz\yii2\media\models\Folder;

class FolderTest extends Unit
{
    public function testFolder(): void
    {
        $folder = Folder::getDefault();
        $this->assertFalse($folder->getIsNewRecord());
    }
}
