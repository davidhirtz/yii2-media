<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Controllers;

use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\Fixtures\FileFixture;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
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
    use MediaFileTrait;

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
        self::assertNotEmpty($this->getWebSession()->getFlash('success'));
    }

    public function testAnEmptySelectionDeletesNothing(): void
    {
        $this->login();
        $file = $this->createFile('first');

        $this->post('admin/media/file/delete-all');

        self::assertNotNull(File::findOne($file->id));
        self::assertEmpty($this->getWebSession()->getFlash('success'));
    }

    public function testDeletingIsForbiddenWithoutThePermission(): void
    {
        $this->getWebUser()->setIdentity($this->getUserFromFixture('admin'));

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

    /**
     * @param array<string, mixed> $bodyParams
     * @param array<string, mixed> $params
     */
    private function post(string $route, array $params = [], array $bodyParams = []): mixed
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $request = $this->getWebRequest();
        $request->setBodyParams([...$bodyParams, $request->csrfParam => $request->getCsrfToken()]);

        return Yii::$app->runAction($route, $params);
    }

    private function login(): User
    {
        $user = $this->getUserFromFixture('admin');

        $permission = Yii::$app->getAuthManager()->getPermission(File::AUTH_FILE);
        Yii::$app->getAuthManager()->assign($permission, $user->id);

        $this->getWebUser()->setIdentity($user);

        return $user;
    }

    private function getUserFromFixture(string $key): User
    {
        /** @var UserFixture $fixture */
        $fixture = $this->getFixture('user');

        return User::findOne($fixture->data[$key]['id']);
    }
}
