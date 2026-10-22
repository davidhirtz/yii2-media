<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Controllers;

use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\File;
use Hirtz\Media\Transformations\Transformation;
use Imagick;
use ImagickPixel;
use Hirtz\Media\Models\Folder;
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

        self::assertMatchesRegularExpression('#^[0-9a-f]{8}/source$#', $this->getLastFile()->basename);
    }

    public function testAPreviewPostedWithTheUploadIsStored(): void
    {
        $this->login();

        $source = $this->path . 'drawing.svg';
        file_put_contents($source, '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>');

        $preview = $this->path . 'preview.jpg';
        imagejpeg(imagecreatetruecolor(200, 100), $preview);

        $_FILES[File::instance()->formName()] = [
            'name' => ['upload' => 'drawing.svg', 'preview' => 'blob'],
            'full_path' => ['upload' => 'drawing.svg', 'preview' => 'blob'],
            'type' => ['upload' => 'image/svg+xml', 'preview' => 'image/jpeg'],
            'tmp_name' => ['upload' => $source, 'preview' => $preview],
            'error' => ['upload' => UPLOAD_ERR_OK, 'preview' => UPLOAD_ERR_OK],
            'size' => ['upload' => filesize($source), 'preview' => filesize($preview)],
        ];

        $this->post('admin/media/file/create');

        $file = $this->getLastFile();

        self::assertSame('svg', $file->extension);
        self::assertSame(1, $file->transformation_count);
        self::assertNotNull($file->getPreviewUrl());
    }

    public function testRenamingOntoATakenNameSaysSo(): void
    {
        $this->login();
        $file = File::findOne(3);
        $taken = File::findOne(1);
        self::assertNotNull($file);
        self::assertNotNull($taken);

        FileHelper::createDirectory(dirname($file->getFilePath()));
        file_put_contents($file->getFilePath(), 'x');
        file_put_contents($taken->getFilePath(), 'x');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = $this->getWebRequest();
        $request->setBodyParams([
            $request->csrfParam => $request->getCsrfToken(),
            'File' => ['basename' => $taken->basename],
        ]);

        Yii::$app->runAction('admin/media/file/update', ['id' => $file->id]);

        self::assertSame($taken->basename . '_1', File::findOne(3)?->basename);
        self::assertNotEmpty($this->getWebSession()->getFlash('warning'));
    }

    public function testSavingAHashedFileUnchangedWarnsOfNothing(): void
    {
        $this->login();
        $file = $this->createFileOnDisk('abcdef12/test-3');

        // the form posts the name without its hash directory
        $this->postForm($file->id, ['basename' => 'test-3', 'name' => 'Renamed title']);

        self::assertSame('abcdef12/test-3', File::findOne(3)?->basename);
        self::assertNull($this->getWebSession()->getFlash('warning'));
    }

    public function testSavingASanitizedNameWarnsOfNothing(): void
    {
        $this->login();
        $file = $this->createFileOnDisk('test-3');

        $this->postForm($file->id, ['basename' => 'Mein Bild Ölçü']);

        self::assertSame('Mein_Bild_Olcu', File::findOne(3)?->basename);
        self::assertNull($this->getWebSession()->getFlash('warning'));
    }

    public function testReplacingAFileWarnsOfNothing(): void
    {
        $this->login();
        $file = $this->createFileOnDisk('test-3');

        $this->setUpUpload($this->createSourceFile());
        $this->post('admin/media/file/update', ['id' => $file->id]);

        self::assertNotEmpty($this->getWebSession()->getFlash('success'));
        self::assertNull($this->getWebSession()->getFlash('warning'));
    }

    public function testADuplicatedPdfKeepsItsPreview(): void
    {
        $file = $this->uploadPdfWithPreview();
        self::assertNotNull($file->getPreviewUrl());

        $this->post('admin/media/file/duplicate', ['id' => $file->id]);

        $duplicate = $this->getLastFile();
        self::assertNotSame($file->id, $duplicate->id);
        self::assertSame(1, $duplicate->transformation_count);
        self::assertNotNull($duplicate->getPreviewUrl());

        $preview = $duplicate->getTransformations()->andWhere(['name' => Transformation::NAME_ADMIN])->one();
        self::assertNotNull($preview);
        self::assertFileExists($preview->getFilePath());
    }

    public function testAPreviewPostedWithAPdfIsStored(): void
    {
        $file = $this->uploadPdfWithPreview();

        self::assertSame('pdf', $file->extension);
        self::assertSame(1, $file->transformation_count);
        self::assertNotNull($file->getPreviewUrl());
    }

    private function uploadPdfWithPreview(): File
    {
        File::getModule()->allowedExtensions = ['pdf'];

        $this->login();

        $source = $this->path . 'document.pdf';
        file_put_contents($source, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");

        $preview = $this->path . 'preview.webp';
        imagewebp(imagecreatetruecolor(640, 905), $preview);

        $_FILES[File::instance()->formName()] = [
            'name' => ['upload' => 'document.pdf', 'preview' => 'blob'],
            'full_path' => ['upload' => 'document.pdf', 'preview' => 'blob'],
            'type' => ['upload' => 'application/pdf', 'preview' => 'image/webp'],
            'tmp_name' => ['upload' => $source, 'preview' => $preview],
            'error' => ['upload' => UPLOAD_ERR_OK, 'preview' => UPLOAD_ERR_OK],
            'size' => ['upload' => filesize($source), 'preview' => filesize($preview)],
        ];

        $this->post('admin/media/file/create');

        return $this->getLastFile();
    }

    public function testAnUploadWithoutHashDirectoriesIsStoredByItsNameAlone(): void
    {
        File::getModule()->hashDirectories = false;

        $this->login();
        $this->setUpUpload($this->createSourceFile());

        $this->post('admin/media/file/create');

        self::assertSame('source', $this->getLastFile()->basename);
    }

    /**
     * @see https://github.com/davidhirtz/yii2-monorepo/issues/360
     */
    public function testAnUploadLandsInTheRequestedFolder(): void
    {
        $folder = Folder::create();
        $folder->name = 'Upload target';
        self::assertTrue($folder->insert(), print_r($folder->getErrors(), true));
        self::assertNotSame(FolderCollection::getDefault()->id, $folder->id);

        $this->login();
        $this->setUpUpload($this->createSourceFile());

        $this->post('admin/media/file/create', ['folder' => $folder->id]);

        self::assertSame($folder->id, $this->getLastFile()->folder_id);
        self::assertSame(1, Folder::findOne($folder->id)?->file_count);
    }

    /**
     * A file already on disk belongs to nobody the database knows about, so only the file system can report it —
     * and the upload would silently overwrite it.
     */
    public function testAnUploadDoesNotOverwriteAFileNoRecordKnowsAbout(): void
    {
        File::getModule()->hashDirectories = false;
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

        self::assertStringEndsWith('/Ubergrossen_Bild', $this->getLastFile()->basename);
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

        $response = $this->post('admin/media/file/create');

        self::assertSame($count, (int)File::find()->count());
        $this->assertUploadRefused($response);
    }

    public function testAnUploadOverTheServerLimitIsReportedToTheUser(): void
    {
        $this->login();
        $this->setUpUpload('', error: UPLOAD_ERR_INI_SIZE, size: 0);

        $count = (int)File::find()->count();

        $response = $this->post('admin/media/file/create');

        self::assertSame($count, (int)File::find()->count());
        $this->assertUploadRefused($response);
    }

    /**
     * The uploader shows the message, and HTTP/2 has no reason phrase to carry it (monorepo issue #410).
     */
    public function testARefusedReplacementSaysWhyBeyondTheReasonPhrase(): void
    {
        $this->login();
        $this->setUpUpload('', error: UPLOAD_ERR_INI_SIZE, size: 0);

        $file = File::find()->one();
        self::assertNotNull($file);

        $response = $this->post('admin/media/file/update', ['id' => $file->id]);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(400, $response->getStatusCode());

        $message = rawurldecode((string)$response->getHeaders()->get('X-Upload-Error'));

        self::assertNotSame('', $message);
        self::assertSame($response->statusText, $message);
    }

    /**
     * The first chunk names the total, so an upload past the `upload` component's ceiling is refused before any of
     * it is written.
     */
    public function testAnUploadOverTheMaximumSizeIsRefused(): void
    {
        $this->login();

        $this->setUpUpload($this->createSourceFile('abc'));
        $this->getWebRequest()->getHeaders()->set('content-range', 'bytes 0-2/' . (Upload::getComponent()->maxSize + 1));

        $count = (int)File::find()->count();
        $response = $this->post('admin/media/file/create');

        self::assertSame($count, (int)File::find()->count());
        $this->assertUploadRefused($response);
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
     * The header tells the dimensions of a truncated image; only decoding it shows it cannot be cropped or derived.
     */
    public function testAnImageThatCannotBeDecodedIsRefused(): void
    {
        $image = new Imagick();
        $image->newImage(400, 300, new ImagickPixel('red'), 'jpeg');
        $blob = $image->getImageBlob();

        // Cut right after the start of scan: the header is whole, the image data is gone
        $path = $this->createSourceFile(substr($blob, 0, (int)strpos($blob, "\xFF\xDA") + 4));
        self::assertNotFalse(getimagesize($path));

        $file = File::create();
        $file->upload = new ChunkedUploadedFile([
            'name' => 'broken.jpg',
            'tempName' => $path,
            'type' => 'image/jpeg',
            'size' => (int)filesize($path),
            'error' => UPLOAD_ERR_OK,
        ]);

        self::assertFalse($file->validate());
        self::assertArrayHasKey('upload', $file->getErrors());
    }

    public function testSvgMarkupNamedAsAJpegIsRefused(): void
    {
        $path = $this->createSourceFile('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>');

        $file = File::create();
        $file->upload = new ChunkedUploadedFile([
            'name' => 'photo.jpg',
            'tempName' => $path,
            'type' => 'image/jpeg',
            'size' => (int)filesize($path),
            'error' => UPLOAD_ERR_OK,
        ]);

        self::assertFalse($file->validate());
        self::assertArrayHasKey('upload', $file->getErrors());
        self::assertNull($file->width);
    }

    public function testAnUploadedSvgIsStoredWithoutScripts(): void
    {
        $this->login();
        $this->setUpUpload(
            $this->createSourceFile((string)file_get_contents(__DIR__ . '/../../../Data/malicious.svg')),
            name: 'Åsa Привет.svg',
            type: 'image/svg+xml',
        );

        $this->post('admin/media/file/create');

        $file = $this->getLastFile();
        $svg = (string)file_get_contents($file->getFilePath());

        self::assertSame('svg', $file->extension);
        self::assertSame(strlen($svg), $file->size);
        self::assertSame([120, 60], [$file->width, $file->height]);
        self::assertStringContainsString('Привет', $svg);

        foreach (['<script', 'onload', 'onclick', 'foreignObject', 'javascript:', 'evil.example'] as $needle) {
            self::assertStringNotContainsStringIgnoringCase($needle, $svg);
        }
    }

    public function testAnSvgThatDoesNotParseIsRefused(): void
    {
        $path = $this->createSourceFile('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)');

        $file = File::create();
        $file->upload = new ChunkedUploadedFile([
            'name' => 'broken.svg',
            'tempName' => $path,
            'type' => 'image/svg+xml',
            'size' => (int)filesize($path),
            'error' => UPLOAD_ERR_OK,
        ]);

        self::assertFalse($file->validate());
        self::assertArrayHasKey('upload', $file->getErrors());
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

    /**
     * The uploader swaps a `200` into the page, which an empty body removed (monorepo issue #555).
     */
    public function testAChunkAfterAMissingOneIsRefused(): void
    {
        $this->login();

        $this->setUpUpload($this->createSourceFile('abc'));
        $this->getWebRequest()->getHeaders()->set('content-range', 'bytes 6-8/9');

        $count = (int)File::find()->count();
        $response = $this->post('admin/media/file/create');

        self::assertSame($count, (int)File::find()->count());
        $this->assertUploadRefused($response);
    }

    private function assertUploadRefused(mixed $response): void
    {
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(400, $response->getStatusCode());
        self::assertNotSame('', rawurldecode((string)$response->getHeaders()->get('X-Upload-Error')));
        self::assertNull($this->getWebSession()->getFlash('danger'));
    }

    private function createSourceFile(?string $content = null): string
    {
        $path = $this->path . 'source.jpg';

        if ($content === null) {
            imagejpeg(imagecreatetruecolor(2, 1), $path);
        } else {
            file_put_contents($path, $content);
        }

        return $path;
    }

    private function createFileOnDisk(string $basename): File
    {
        $file = File::findOne(3) ?? self::fail('The fixture file is missing.');
        $file->updateAttributes(['basename' => $basename]);

        FileHelper::createDirectory(dirname($file->getFilePath()));
        file_put_contents($file->getFilePath(), 'x');

        return $file;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function postForm(int $id, array $attributes): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = $this->getWebRequest();
        $request->setBodyParams([$request->csrfParam => $request->getCsrfToken(), 'File' => $attributes]);

        Yii::$app->runAction('admin/media/file/update', ['id' => $id]);
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
        string $type = 'image/jpeg',
    ): void {
        $_FILES[File::instance()->formName()] = [
            'name' => ['upload' => $name],
            'full_path' => ['upload' => $name],
            'type' => ['upload' => $type],
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
