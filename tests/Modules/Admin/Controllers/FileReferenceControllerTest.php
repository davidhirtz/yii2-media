<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Controllers;

use Hirtz\Media\Models\CustomAttributes\FileCustomAttribute;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\Models\FileAttributeRecord;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\FileAttributeRecordTrait;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Models\CustomAttributes\GroupCustomAttribute;
use Hirtz\Skeleton\Models\User;
use Hirtz\Skeleton\Test\Fixtures\UserFixture;
use Override;
use Yii;

/**
 * The file's uses: its tab, the column of the file grid and what the delete confirmation tells.
 */
class FileReferenceControllerTest extends TestCase
{
    use FileAttributeRecordTrait;
    use MediaFileTrait;

    private File $file;

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

        Yii::$container->set(FileAttributeRecord::class, [
            'customAttributes' => [
                FileCustomAttribute::make('icon')
                    ->label('Ícone')
                    ->required(),
                GroupCustomAttribute::make('gallery')
                    ->label('Галерея')
                    ->multiple()
                    ->attributes([FileCustomAttribute::make('image')->label('Bild')]),
            ],
        ]);

        $this->folder = Folder::findOne(1) ?? self::fail('The folder fixture was not loaded.');
        FileHelper::createDirectory($this->folder->getUploadPath());

        $this->file = $this->createFile('photo');
        $this->login();
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        Yii::$container->clear(FileAttributeRecord::class);

        parent::tearDown();
    }

    #[Override]
    protected function setUpSchema(): void
    {
        $this->createFileAttributeRecordTable();
    }

    #[Override]
    protected function tearDownSchema(): void
    {
        $this->dropFileAttributeRecordTable();
    }

    public function testWithoutUsesThereIsNeitherTabNorColumn(): void
    {
        $update = Yii::$app->runAction('admin/media/file/update', ['id' => $this->file->id]);
        $index = Yii::$app->runAction('admin/media/file/index');

        self::assertIsString($update);
        self::assertIsString($index);
        self::assertStringNotContainsString('file-reference/index', $update);
        self::assertStringNotContainsString('file-reference/index', $index);
    }

    public function testAUseShowsTheTabTheColumnAndTheCountsInTheDeleteConfirmation(): void
    {
        $this->createFileAttributeRecord([
            'icon' => $this->file->id,
            'gallery' => [['image' => $this->file->id], ['image' => $this->file->id]],
        ]);

        $update = Yii::$app->runAction('admin/media/file/update', ['id' => $this->file->id]);
        $index = Yii::$app->runAction('admin/media/file/index');

        self::assertIsString($update);
        self::assertIsString($index);
        self::assertStringContainsString("file-reference/index?file={$this->file->id}", $update);
        self::assertStringContainsString("file-reference/index?file={$this->file->id}", $index);
        self::assertStringContainsString('It is used 3 times in one record, which lose it.', $update);
        self::assertStringContainsString('One of these uses is required', $update);
    }

    public function testTheGridNamesTheRecordAndTheAttributesAndLinksOnlyWhatTheUserMayOpen(): void
    {
        $record = $this->createFileAttributeRecord([
            'icon' => $this->file->id,
            'gallery' => [['image' => $this->file->id], ['image' => $this->file->id]],
        ], 'Å Москва 東京');

        $html = Yii::$app->runAction('admin/media/file-reference/index', ['file' => $this->file->id]);

        self::assertIsString($html);
        self::assertStringContainsString('Å Москва 東京', $html);
        self::assertStringContainsString('Галерея › Bild (2×), Ícone', $html);
        self::assertStringNotContainsString("/admin/test/update?id=$record->id", $html);

        $auth = Yii::$app->getAuthManager();
        $permission = $auth->createPermission('fileAttributeRecord');
        $auth->add($permission);
        $auth->assign($permission, (int)$this->getWebUser()->getId());
        $this->getWebUser()->setIdentity($this->getWebUser()->getIdentity());

        $html = Yii::$app->runAction('admin/media/file-reference/index', ['file' => $this->file->id]);

        self::assertIsString($html);
        self::assertStringContainsString("/admin/test/update?id=$record->id", $html);
    }

    private function login(): void
    {
        /** @var UserFixture $fixture */
        $fixture = $this->getFixture('user');
        $user = User::findOne($fixture->data['admin']['id']) ?? self::fail('The user fixture was not loaded.');

        $permission = Yii::$app->getAuthManager()->getPermission(File::AUTH_FILE);
        self::assertNotNull($permission);
        Yii::$app->getAuthManager()->assign($permission, $user->id);

        $this->getWebUser()->setIdentity($user);
    }
}
