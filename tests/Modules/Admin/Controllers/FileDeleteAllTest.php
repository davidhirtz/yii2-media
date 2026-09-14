<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Controllers;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\Fixtures\FileFixture;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Models\User;
use Hirtz\Skeleton\Test\Fixtures\UserFixture;
use Override;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

class FileDeleteAllTest extends TestCase
{
    private Folder $folder;

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
        FileHelper::createDirectory($this->folder->getUploadPath());
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        parent::tearDown();
    }

    public function testTheSelectedFilesAreDeleted(): void
    {
        $this->login();

        $first = $this->createFile('first');
        $second = $this->createFile('second');
        $third = $this->createFile('third');

        $response = $this->post('admin/media/file/delete-all', [], [
            'selection' => [(string)$first->id, (string)$second->id],
        ]);

        self::assertInstanceOf(Response::class, $response);

        self::assertNull(File::findOne($first->id));
        self::assertNull(File::findOne($second->id));
        self::assertNotNull(File::findOne($third->id));

        self::assertSame(7, Folder::findOne($this->folder->id)->file_count);
        self::assertNotEmpty(Yii::$app->getSession()->getFlash('success'));
    }

    public function testAnEmptySelectionDeletesNothing(): void
    {
        $this->login();
        $file = $this->createFile('first');

        $this->post('admin/media/file/delete-all');

        self::assertNotNull(File::findOne($file->id));
        self::assertEmpty(Yii::$app->getSession()->getFlash('success'));
    }

    public function testDeletingIsForbiddenWithoutThePermission(): void
    {
        Yii::$app->getUser()->setIdentity($this->getUserFromFixture('admin'));

        $this->expectException(ForbiddenHttpException::class);
        $this->post('admin/media/file/delete-all');
    }

    public function testDeleteAllRefusesAGetRequest(): void
    {
        $this->login();

        $this->expectException(MethodNotAllowedHttpException::class);
        Yii::$app->runAction('admin/media/file/delete-all');
    }

    /**
     * The row's own delete button is gone, so the footer is the only way to delete from the grid.
     */
    public function testTheGridOffersTheSelection(): void
    {
        $this->login();
        $this->createFile('first');

        $html = (string)Yii::$app->runAction('admin/media/file/index');

        self::assertStringContainsString('name="selection[]"', $html);
        self::assertStringContainsString('/admin/media/file/delete-all', $html);
        self::assertStringNotContainsString('/admin/media/file/delete?', $html);
    }

    private function createFile(string $basename): File
    {
        $path = $this->folder->getUploadPath() . "$basename.jpg";
        $this->writeImage($path);

        $file = File::create();
        $file->loadDefaultValues();
        $file->name = ucfirst($basename);
        $file->basename = $basename;
        $file->extension = 'jpg';
        $file->width = 100;
        $file->height = 100;
        $file->size = filesize($path);
        $file->populateFolderRelation($this->folder);

        self::assertTrue($file->insert(), print_r($file->getErrors(), true));

        return $file;
    }

    private function writeImage(string $path): void
    {
        FileHelper::createDirectory(dirname($path));

        $image = imagecreatetruecolor(100, 100);
        imagejpeg($image, $path);
        imagedestroy($image);
    }

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
