<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Widgets\Navs;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\Admin\Widgets\Navs\FolderActionDropdown;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Test\Traits\UserFixtureTrait;

class FolderActionDropdownTest extends TestCase
{
    use UserFixtureTrait;

    public function testAFolderHoldingFilesLinksToThem(): void
    {
        $this->loginWithFilePermission();

        self::assertStringContainsString(
            '/admin/media/file/index?folder=1',
            (string)FolderActionDropdown::make()->model($this->getFolder(3))
        );
    }

    public function testAnEmptyFolderLinksNowhere(): void
    {
        $this->loginWithFilePermission();

        self::assertStringNotContainsString(
            '/admin/media/file/index',
            (string)FolderActionDropdown::make()->model($this->getFolder(0))
        );
    }

    public function testTheLinkNeedsTheFilePermission(): void
    {
        $this->getWebUser()->setIdentity($this->getUserFromFixture('admin'));

        self::assertStringNotContainsString(
            '/admin/media/file/index',
            (string)FolderActionDropdown::make()->model($this->getFolder(3))
        );
    }

    protected function loginWithFilePermission(): void
    {
        $user = $this->getUserFromFixture('admin');
        $this->assignPermission($user->id, File::AUTH_FILE);

        $this->getWebUser()->setIdentity($user);
    }

    protected function getFolder(int $fileCount): Folder
    {
        $folder = Folder::create();
        $folder->id = 1;
        $folder->name = 'Default';
        $folder->file_count = $fileCount;

        return $folder;
    }
}
