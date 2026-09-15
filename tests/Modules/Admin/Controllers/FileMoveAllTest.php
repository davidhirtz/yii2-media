<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Controllers;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Modules\Admin\Data\FileActiveDataProvider;
use Hirtz\Media\Modules\Admin\Widgets\Grids\FileGridView;
use Hirtz\Media\Test\Models\TestAssetModel;
use Hirtz\Media\Test\Fixtures\FileFixture;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Models\User;
use Hirtz\Skeleton\Web\Controller;
use Hirtz\Skeleton\Test\Fixtures\UserFixture;
use Override;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class FileMoveAllTest extends TestCase
{
    private Folder $folder;
    private Folder $target;

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function fixtures(): array
    {
        return [
            'folder' => FolderFixture::class,
            'file' => FileFixture::class,
            'user' => UserFixture::class,
        ];
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = Folder::findOne(1);
        $this->target = $this->createFolder('Archive', 'archive');

        FileHelper::createDirectory($this->folder->getUploadPath());
        FileHelper::createDirectory($this->target->getUploadPath());
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        parent::tearDown();
    }

    public function testTheSelectedFilesAreMoved(): void
    {
        $this->login();

        $first = $this->createFile('first');
        $second = $this->createFile('second');
        $third = $this->createFile('third');

        $response = $this->post('admin/media/file/move-all', [], [
            'folder' => (string)$this->target->id,
            'selection' => [(string)$first->id, (string)$second->id],
        ]);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame($this->target->id, File::findOne($first->id)->folder_id);
        self::assertSame($this->target->id, File::findOne($second->id)->folder_id);
        self::assertSame($this->folder->id, File::findOne($third->id)->folder_id);
        self::assertNotEmpty(Yii::$app->getSession()->getFlash('success'));
    }

    public function testAnEmptySelectionChangesNothing(): void
    {
        $this->login();
        $file = $this->createFile('first');

        $this->post('admin/media/file/move-all', [], ['folder' => (string)$this->target->id]);

        self::assertSame($this->folder->id, File::findOne($file->id)->folder_id);
        self::assertEmpty(Yii::$app->getSession()->getFlash('success'));
    }

    public function testAnUnknownFolderIsNotFound(): void
    {
        $this->login();

        $this->expectException(NotFoundHttpException::class);
        $this->post('admin/media/file/move-all', [], ['folder' => '99999']);
    }

    public function testMovingIsForbiddenWithoutThePermission(): void
    {
        Yii::$app->getUser()->setIdentity($this->getUserFromFixture('admin'));

        $this->expectException(ForbiddenHttpException::class);
        $this->post('admin/media/file/move-all', [], ['folder' => (string)$this->target->id]);
    }

    /**
     * A rename is silent otherwise, and a batch can rename many files at once.
     */
    public function testARenamedFileIsReported(): void
    {
        $this->login();

        $file = $this->createFile('photo');
        $this->createFile('photo', $this->target);

        $this->post('admin/media/file/move-all', [], [
            'folder' => (string)$this->target->id,
            'selection' => [(string)$file->id],
        ]);

        self::assertSame('photo_1', File::findOne($file->id)->basename);
        self::assertNotEmpty(Yii::$app->getSession()->getFlash('warning'));
    }

    /**
     * With no second folder there is nowhere to move a selection to, and only the move item goes — the selection
     * itself stays, since its other item deletes.
     */
    public function testTheGridOffersTheMoveOnlyWithASecondFolder(): void
    {
        $this->login();
        $this->createFile('first');

        $html = (string)Yii::$app->runAction('admin/media/file/index');

        self::assertStringContainsString('name="selection[]"', $html);
        self::assertStringContainsString('/admin/media/file/move-all', $html);
        self::assertStringContainsString('name="folder"', $html);

        $this->target->delete();
        Yii::$app->getSession()->removeAllFlashes();

        $html = (string)Yii::$app->runAction('admin/media/file/index');

        self::assertStringContainsString('name="selection[]"', $html);
        self::assertStringNotContainsString('/admin/media/file/move-all', $html);
    }

    /**
     * A picker is a grid the user chooses a file *from*, so it must not offer an action that leads out of it.
     */
    public function testAPickerOffersNoSelection(): void
    {
        $this->login();
        $this->createFile('first');

        Yii::$app->controller = new Controller('test', Yii::$app);

        $model = TestAssetModel::create();
        $model->setAttributes(['id' => 1, 'asset_count' => 0], false);
        $model->populateRelation('assets', []);

        $html = (string)FileGridView::make()
            ->model($model)
            ->provider(Yii::createObject(FileActiveDataProvider::class));

        self::assertStringNotContainsString('name="selection[]"', $html);
        self::assertStringNotContainsString('/admin/media/file/move-all', $html);
    }

    private function createFolder(string $name, string $path): Folder
    {
        $folder = Folder::create();
        $folder->loadDefaultValues();
        $folder->name = $name;
        $folder->path = $path;

        self::assertTrue($folder->insert(), print_r($folder->getErrors(), true));

        return $folder;
    }

    private function createFile(string $basename, ?Folder $folder = null): File
    {
        $folder ??= $this->folder;
        $path = $folder->getUploadPath() . "$basename.jpg";
        $this->writeImage($path);

        $file = File::create();
        $file->loadDefaultValues();
        $file->name = ucfirst($basename);
        $file->basename = $basename;
        $file->extension = 'jpg';
        $file->width = 100;
        $file->height = 100;
        $file->size = filesize($path);
        $file->populateFolderRelation($folder);

        self::assertTrue($file->insert(), print_r($file->getErrors(), true));

        if (!is_file($file->getFilePath())) {
            $this->writeImage($file->getFilePath());
        }

        return $file;
    }

    private function writeImage(string $path): void
    {
        FileHelper::createDirectory(dirname($path));

        $image = imagecreatetruecolor(100, 100);
        imagejpeg($image, $path);
        imagedestroy($image);
    }

    /**
     * @param array<string, mixed> $bodyParams
     * @param array<string, mixed> $params
     */
    private function post(string $route, array $params = [], array $bodyParams = []): mixed
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $request = Yii::$app->getRequest();
        $request->setBodyParams([...$bodyParams, $request->csrfParam => $request->getCsrfToken()]);

        return Yii::$app->runAction($route, $params);
    }

    private function login(): User
    {
        $user = $this->getUserFromFixture('admin');

        $permission = Yii::$app->getAuthManager()->getPermission(File::AUTH_FILE);
        Yii::$app->getAuthManager()->assign($permission, $user->id);

        Yii::$app->getUser()->setIdentity($user);

        return $user;
    }

    private function getUserFromFixture(string $key): User
    {
        /** @var UserFixture $fixture */
        $fixture = $this->getFixture('user');

        return User::findOne($fixture->data[$key]['id']);
    }
}
