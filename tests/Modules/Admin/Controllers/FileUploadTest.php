<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Controllers;

use Hirtz\Media\Models\File;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Fixtures\FileFixture;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Models\User;
use Hirtz\Skeleton\Test\Fixtures\UserFixture;
use Override;
use Yii;
use yii\web\Response;

class FileUploadTest extends TestCase
{
    private string $path;

    /**
     * `MediaFixtureTrait` and `UserFixtureTrait` both declare `fixtures()`, so a test that needs both lists them
     * itself rather than aliasing either.
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

        $this->path = Yii::getAlias('@runtime/test-uploads') . '/';
        FileHelper::createDirectory($this->path);
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory($this->path);
        parent::tearDown();
    }

    public function testAnUploadedFileIsCreated(): void
    {
        $this->login();
        $this->setUpUpload($this->createSourceFile());

        $count = (int)File::find()->count();
        $response = $this->post('admin/media/file/create');

        self::assertInstanceOf(Response::class, $response);
        self::assertSame($count + 1, (int)File::find()->count());
    }

    /**
     * A browser that aborts mid-upload leaves PHP with `UPLOAD_ERR_PARTIAL` and an empty `tmp_name`. That used to
     * reach `fopen('')` and throw a `ValueError` before the request ever got to the model.
     *
     * @see https://github.com/davidhirtz/yii2-monorepo/issues/27
     */
    public function testAnAbortedUploadIsReportedToTheUser(): void
    {
        $this->login();
        $this->setUpUpload('', error: UPLOAD_ERR_PARTIAL, size: 0);

        $count = (int)File::find()->count();

        $this->post('admin/media/file/create');

        self::assertSame($count, (int)File::find()->count());
        self::assertNotEmpty(Yii::$app->getSession()->getFlash('danger'));
    }

    public function testAnUploadOverTheServerLimitIsReportedToTheUser(): void
    {
        $this->login();
        $this->setUpUpload('', error: UPLOAD_ERR_INI_SIZE, size: 0);

        $count = (int)File::find()->count();

        $this->post('admin/media/file/create');

        self::assertSame($count, (int)File::find()->count());
        self::assertNotEmpty(Yii::$app->getSession()->getFlash('danger'));
    }

    /**
     * A chunk that really did land asks the uploader for the next one; a transfer that broke must not.
     */
    public function testAChunkThatLandedAsksForTheNext(): void
    {
        $this->login();

        $source = $this->createSourceFile('abc');
        $this->setUpUpload($source);

        Yii::$app->getRequest()->getHeaders()->set('content-range', 'bytes 0-2/9');

        $count = (int)File::find()->count();
        $response = $this->post('admin/media/file/create');

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(201, $response->getStatusCode());
        self::assertSame($count, (int)File::find()->count());
    }

    private function createSourceFile(string $content = 'abc'): string
    {
        $path = $this->path . 'source.jpg';
        file_put_contents($path, $content);

        return $path;
    }

    private function setUpUpload(string $tempName, int $error = UPLOAD_ERR_OK, ?int $size = null): void
    {
        $_FILES[File::instance()->formName()] = [
            'name' => ['upload' => 'source.jpg'],
            'full_path' => ['upload' => 'source.jpg'],
            'type' => ['upload' => 'image/jpeg'],
            'tmp_name' => ['upload' => $tempName],
            'error' => ['upload' => $error],
            'size' => ['upload' => $size ?? ($tempName ? filesize($tempName) : 0)],
        ];
    }

    private function post(string $route, array $params = []): mixed
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $request = Yii::$app->getRequest();
        $request->setBodyParams([$request->csrfParam => $request->getCsrfToken()]);

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
