<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Controllers;

use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\File;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Fixtures\FileFixture;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Models\User;
use Hirtz\Skeleton\Test\Fixtures\UserFixture;
use Hirtz\Skeleton\Upload\Upload;
use Hirtz\Skeleton\Web\ChunkedUploadedFile;
use Override;
use Yii;
use yii\helpers\StringHelper;
use yii\web\Response;

class FileUploadTest extends TestCase
{
    private string $path;

    /**
     * `MediaFixtureTrait` and `UserFixtureTrait` both declare `fixtures()`, so a test that needs both lists them
     * itself rather than aliasing either.
     *
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

        $this->path = Yii::getAlias('@runtime/test-uploads') . '/';
        FileHelper::createDirectory($this->path);
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory($this->path);
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
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

    public function testAnUploadKeepsItsOwnFilename(): void
    {
        $this->login();
        $this->setUpUpload($this->createSourceFile());

        $this->post('admin/media/file/create');

        self::assertSame('source', $this->getLastFile()->basename);
    }

    /**
     * A file already on disk belongs to nobody the database knows about, so only the file system can report it —
     * and the upload would silently overwrite it.
     */
    public function testAnUploadDoesNotOverwriteAFileNoRecordKnowsAbout(): void
    {
        $this->login();

        $orphan = $this->getUploadPath() . 'source.jpg';
        FileHelper::createDirectory(dirname($orphan));
        file_put_contents($orphan, 'orphan');

        $this->setUpUpload($this->createSourceFile());
        $this->post('admin/media/file/create');

        self::assertSame('source_1', $this->getLastFile()->basename);
        self::assertSame('orphan', file_get_contents($orphan));
    }

    /**
     * The basename is stripped down to what a URL may carry, so a filename outside ASCII has to be transliterated
     * first — it would be stripped to nothing otherwise.
     */
    public function testANonAsciiFilenameIsTransliterated(): void
    {
        $this->login();
        $this->setUpUpload($this->createSourceFile(), name: 'Übergrößen Bild.jpg');

        $this->post('admin/media/file/create');

        self::assertSame('Ubergrossen_Bild', $this->getLastFile()->basename);
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
        self::assertNotEmpty($this->getWebSession()->getFlash('danger'));
    }

    public function testAnUploadOverTheServerLimitIsReportedToTheUser(): void
    {
        $this->login();
        $this->setUpUpload('', error: UPLOAD_ERR_INI_SIZE, size: 0);

        $count = (int)File::find()->count();

        $this->post('admin/media/file/create');

        self::assertSame($count, (int)File::find()->count());
        self::assertNotEmpty($this->getWebSession()->getFlash('danger'));
    }

    /**
     * The first chunk names the total, so an upload past the `upload` component's ceiling is refused before any of
     * it is written.
     */
    public function testAnUploadOverTheMaximumSizeIsRefused(): void
    {
        $this->login();

        $this->setUpUpload($this->createSourceFile());
        $this->getWebRequest()->getHeaders()->set('content-range', 'bytes 0-2/' . (Upload::getComponent()->maxSize + 1));

        $count = (int)File::find()->count();
        $this->post('admin/media/file/create');

        self::assertSame($count, (int)File::find()->count());
        self::assertNotEmpty($this->getWebSession()->getFlash('danger'));
    }

    /**
     * A file assembled from chunks is past php.ini's `upload_max_filesize` as often as not.
     */
    public function testAFileLargerThanASingleRequestIsValid(): void
    {
        $file = File::create();
        $file->upload = new ChunkedUploadedFile([
            'name' => 'large.jpg',
            'tempName' => $this->createSourceFile(),
            'type' => 'image/jpeg',
            'size' => StringHelper::convertIniSizeToBytes((string)ini_get('upload_max_filesize')) + 1,
            'error' => UPLOAD_ERR_OK,
        ]);

        self::assertTrue($file->validate(['upload']), implode(' ', $file->getErrorSummary(true)));

        $file->upload->size = Upload::getComponent()->maxSize + 1;
        self::assertFalse($file->validate(['upload']));
    }

    /**
     * A chunk that really did land asks the uploader for the next one; a transfer that broke must not.
     */
    public function testAChunkThatLandedAsksForTheNext(): void
    {
        $this->login();

        $source = $this->createSourceFile('abc');
        $this->setUpUpload($source);

        $this->getWebRequest()->getHeaders()->set('content-range', 'bytes 0-2/9');

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

    private function getUploadPath(): string
    {
        return FolderCollection::getDefault()->getUploadPath();
    }

    private function getLastFile(): File
    {
        return File::find()->orderBy(['id' => SORT_DESC])->limit(1)->one();
    }

    private function setUpUpload(
        string $tempName,
        int $error = UPLOAD_ERR_OK,
        ?int $size = null,
        string $name = 'source.jpg',
    ): void {
        $_FILES[File::instance()->formName()] = [
            'name' => ['upload' => $name],
            'full_path' => ['upload' => $name],
            'type' => ['upload' => 'image/jpeg'],
            'tmp_name' => ['upload' => $tempName],
            'error' => ['upload' => $error],
            'size' => ['upload' => $size ?? ($tempName ? filesize($tempName) : 0)],
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function post(string $route, array $params = []): mixed
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $request = $this->getWebRequest();
        $request->setBodyParams([$request->csrfParam => $request->getCsrfToken()]);

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
