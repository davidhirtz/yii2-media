<?php

declare(strict_types=1);

namespace Hirtz\Media\Test;

use Hirtz\Media\Models\Collections\FolderCollection;
use Override;

class TestCase extends \Hirtz\Skeleton\Test\TestCase
{
    #[Override]
    protected function setUp(): void
    {
        $this->config ??= require(__DIR__ . '/../../config/test.php');
        parent::setUp();

        FolderCollection::invalidateCache();
    }

    protected function tearDown(): void
    {
        FolderCollection::invalidateCache();
        parent::tearDown();
    }
}
