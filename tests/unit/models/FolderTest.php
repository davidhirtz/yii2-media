<?php

namespace davidhirtz\yii2\media\tests\unit\models;

use Codeception\Test\Unit;
use davidhirtz\yii2\media\models\collections\FolderCollection;

class FolderTest extends Unit
{
    public function testFolder(): void
    {
        $folder = FolderCollection::getDefault();
        $this->assertFalse($folder->getIsNewRecord());
    }
}
