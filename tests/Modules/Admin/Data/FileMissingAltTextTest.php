<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Data;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\Admin\Data\FileActiveDataProvider;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Models\User;
use Hirtz\Skeleton\Test\Fixtures\UserFixture;
use Hirtz\Skeleton\Widgets\Panels\Dashboard;
use Override;
use Yii;

class FileMissingAltTextTest extends TestCase
{
    use MediaFileTrait;

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function fixtures(): array
    {
        return [
            'folder' => FolderFixture::class,
            'user' => UserFixture::class,
        ];
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->folder = Folder::findOne(1) ?? self::fail('Folder fixture missing.');
        FileHelper::createDirectory($this->folder->getUploadPath());
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        parent::tearDown();
    }

    public function testOnlyImagesInUseWithoutAltTextAreListed(): void
    {
        $asset = $this->insertFile('asset', assetCount: 1);
        $referenced = $this->insertFile('referenced', referenceCount: 2);
        $blank = $this->insertFile('blank', altText: '  ', assetCount: 1);
        $described = $this->insertFile('described', altText: 'Ålesund ą 東京 😀', assetCount: 1);
        $unused = $this->insertFile('unused');
        $document = $this->insertFile('document', assetCount: 1, isImage: false);
        $video = $this->insertFile('video', assetCount: 1);
        $video->updateAttributes(['extension' => 'mp4']);

        $ids = array_map(intval(...), File::find()->whereMissingAltText()->select('id')->column());

        self::assertContains($asset->id, $ids);
        self::assertContains($referenced->id, $ids);
        self::assertContains($blank->id, $ids);
        self::assertNotContains($described->id, $ids);
        self::assertNotContains($unused->id, $ids);
        self::assertNotContains($document->id, $ids);
        self::assertNotContains($video->id, $ids);

        $provider = new FileActiveDataProvider(['missing' => FileActiveDataProvider::MISSING_ALT_TEXT]);
        self::assertSame(count($ids), $provider->getTotalCount());
    }

    public function testTheDashboardLinksTheCountToTheFilteredGrid(): void
    {
        $this->insertFile('dashboard', assetCount: 1);
        $count = (int)File::find()->whereMissingAltText()->count();

        $this->getWebUser()->setIdentity($this->getUserFromFixture());

        $html = Dashboard::make()->render();

        self::assertStringContainsString(Yii::t('media', 'FILE_MISSING_ALT_TEXT_TEXT', ['count' => $count]), $html);
        self::assertStringContainsString(Url::to(['/admin/media/file/index', 'missing' => 'alt_text']), $html);
    }

    private function insertFile(
        string $basename,
        ?string $altText = null,
        int $assetCount = 0,
        int $referenceCount = 0,
        bool $isImage = true,
    ): File {
        $file = $this->createFile("missing-alt-$basename");

        $file->updateAttributes([
            'alt_text' => $altText,
            'asset_count' => $assetCount,
            'reference_count' => $referenceCount,
            ...($isImage ? [] : ['width' => null, 'height' => null]),
        ]);

        return $file;
    }

    private function getUserFromFixture(): User
    {
        // The owner, who may open every grid.
        return User::findOne(1) ?? self::fail('User fixture missing.');
    }
}
