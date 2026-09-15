<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models\Actions;

use Hirtz\Media\Models\Actions\SaveFolderRedirects;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\Fixtures\FileFixture;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Models\Redirect;
use Override;

/**
 * A folder's path is the first segment of every file URL in it, and a rename changes no file record at all — so
 * `RedirectBehavior`, which compares a record's own URL across its save, never sees it.
 */
class SaveFolderRedirectsTest extends TestCase
{
    private Folder $folder;

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function fixtures(): array
    {
        return [
            'folder' => FolderFixture::class,
            'file' => FileFixture::class,
        ];
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = Folder::findOne(1);
        FileHelper::createDirectory($this->folder->getUploadPath());
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        parent::tearDown();
    }

    public function testARenamedPathRedirectsEveryFile(): void
    {
        $first = $this->createFile('first');
        $second = $this->createFile('second');

        $this->renameFolder('archive');

        self::assertRedirect('uploads/default/first.jpg', 'uploads/archive/first.jpg');
        self::assertRedirect('uploads/default/second.jpg', 'uploads/archive/second.jpg');

        self::assertStringContainsString('archive', File::findOne($first->id)->getUrl());
        self::assertStringContainsString('archive', File::findOne($second->id)->getUrl());
    }

    public function testAnUnchangedPathWritesNothing(): void
    {
        $this->createFile('first');

        $before = $this->getRedirectCount();

        $this->folder->name = 'Renamed';
        self::assertSame(1, $this->folder->update());

        self::assertSame($before, $this->getRedirectCount());
    }

    /**
     * The redirect the first rename recorded now points at a URL nothing resolves, so it is carried to the new
     * one rather than left dangling.
     */
    public function testASecondRenameCarriesTheEarlierRedirect(): void
    {
        $this->createFile('first');

        $this->renameFolder('archive');
        $this->renameFolder('storage');

        self::assertRedirect('uploads/default/first.jpg', 'uploads/storage/first.jpg');
        self::assertRedirect('uploads/archive/first.jpg', 'uploads/storage/first.jpg');
    }

    /**
     * Renaming back onto a path the folder already had makes the earlier redirect point at itself, which is not a
     * redirect at all.
     */
    public function testARenameBackDeletesTheRedirect(): void
    {
        $this->createFile('first');

        $this->renameFolder('archive');
        $this->renameFolder('default');

        self::assertNull(Redirect::findOne(['request_uri' => 'uploads/default/first.jpg']));
        self::assertRedirect('uploads/archive/first.jpg', 'uploads/default/first.jpg');
    }

    /**
     * `Redirect::validateUrl()` resolves the chain its target starts, so a row moved onto a URL that another row
     * of the same pass still redirects away from follows it straight back.
     */
    public function testARenameBackCarriesAnUnrelatedRedirectWithIt(): void
    {
        $file = $this->createFile('first');

        // a redirect from somewhere else entirely onto the file, the shape a move between folders leaves behind
        $redirect = Redirect::create();
        $redirect->request_uri = 'uploads/elsewhere/first.jpg';
        $redirect->url = $file->getUrl();
        self::assertTrue($redirect->insert(), print_r($redirect->getErrors(), true));

        $this->renameFolder('archive');
        $this->renameFolder('default');

        self::assertRedirect('uploads/elsewhere/first.jpg', 'uploads/default/first.jpg');
    }

    public function testAFolderOverTheLimitIsRenamedWithoutRedirects(): void
    {
        $this->createFile('first');
        File::getModule()->maxFolderRedirects = 0;

        $before = $this->getRedirectCount();
        $this->renameFolder('archive');

        self::assertSame($before, $this->getRedirectCount());
        self::assertSame('archive', Folder::findOne($this->folder->id)->path);
    }

    public function testTheActionReportsWhatItWrote(): void
    {
        $this->createFile('first');
        $this->createFile('second');

        // the folder is not renamed here, so `Folder::afterSave()` has not already written these
        $action = SaveFolderRedirects::create($this->folder, 'legacy');

        self::assertFalse($action->isSkipped());
        self::assertSame((int)$this->folder->getFiles()->count(), $action->getCount());
        self::assertRedirect('uploads/legacy/first.jpg', 'uploads/default/first.jpg');
    }

    public function testTheActionReportsThatItSkipped(): void
    {
        $this->createFile('first');
        File::getModule()->maxFolderRedirects = 0;

        $action = SaveFolderRedirects::create($this->folder, 'legacy');

        self::assertTrue($action->isSkipped());
        self::assertSame(0, $action->getCount());
        self::assertNull(Redirect::findOne(['request_uri' => 'uploads/legacy/first.jpg']));
    }

    public function testTheLimitIsReportedBeforeTheRename(): void
    {
        $this->createFile('first');
        $folder = Folder::findOne($this->folder->id);

        self::assertTrue(SaveFolderRedirects::isWithinLimit($folder));

        File::getModule()->maxFolderRedirects = 0;
        self::assertFalse(SaveFolderRedirects::isWithinLimit($folder));

        File::getModule()->maxFolderRedirects = false;
        self::assertFalse(SaveFolderRedirects::isWithinLimit($folder));
    }

    private function renameFolder(string $path): void
    {
        $this->folder->path = $path;
        self::assertSame(1, $this->folder->update(), print_r($this->folder->getErrors(), true));
    }

    private static function assertRedirect(string $requestUri, string $url): void
    {
        $redirect = Redirect::findOne(['request_uri' => $requestUri]);

        self::assertInstanceOf(Redirect::class, $redirect, "No redirect from $requestUri");
        self::assertSame($url, $redirect->url);
    }

    private function getRedirectCount(): int
    {
        return (int)Redirect::find()->count();
    }

    private function createFile(string $basename): File
    {
        $path = $this->folder->getUploadPath() . "$basename.jpg";
        FileHelper::createDirectory(dirname($path));

        $image = imagecreatetruecolor(100, 100);
        imagejpeg($image, $path);
        imagedestroy($image);

        $file = File::create();
        $file->loadDefaultValues();
        $file->name = ucfirst($basename);
        $file->basename = $basename;
        $file->extension = 'jpg';
        $file->width = 100;
        $file->height = 100;
        $file->size = filesize($path) ?: 0;
        $file->populateFolderRelation($this->folder);

        self::assertTrue($file->insert(), print_r($file->getErrors(), true));

        return $file;
    }
}
