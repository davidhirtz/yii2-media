<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Controllers;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Models\FileTransformation;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Media\Test\Fixtures\FileFixture;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\Models\TestAssetModel;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Models\User;
use Hirtz\Skeleton\Test\Fixtures\UserFixture;
use Override;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * The file, asset and transformation pages of the media admin.
 */
class MediaAdminTest extends TestCase
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

        File::getModule()->addTransformation(Transformation::make('square')->width(50)->height(50));

        $this->folder = Folder::findOne(1);
        FileHelper::createDirectory($this->folder->getUploadPath());
    }

    #[Override]
    protected function tearDown(): void
    {
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        parent::tearDown();
    }

    public function testTheFileIndexListsTheFiles(): void
    {
        $this->login();

        $html = Yii::$app->runAction('admin/media/file/index');

        self::assertIsString($html);
        self::assertStringContainsString('Test 1', $html);
    }

    public function testTheFileIndexFiltersByFolderAndSearch(): void
    {
        $this->login();

        $html = Yii::$app->runAction('admin/media/file/index', ['q' => 'Test 3']);

        self::assertIsString($html);
        self::assertStringContainsString('Test 3', $html);
        self::assertStringNotContainsString('Test 1<', $html);

        $other = $this->createFolder('Archive', 'archive');

        $html = Yii::$app->runAction('admin/media/file/index', ['folder' => $other->id]);

        self::assertIsString($html);
        self::assertStringNotContainsString('Test 1', $html);
    }

    public function testTheFileUpdatePageRendersTheForm(): void
    {
        $this->login();
        $file = $this->createFile('photo');

        $html = Yii::$app->runAction('admin/media/file/update', ['id' => $file->id]);

        self::assertIsString($html);
        self::assertStringContainsString('name="File[name]"', $html);
    }

    public function testTheFileIsSaved(): void
    {
        $this->login();
        $file = $this->createFile('photo');

        $response = $this->post('admin/media/file/update', ['id' => $file->id], [
            'File' => ['name' => 'Renamed', 'basename' => 'photo', 'extension' => 'jpg'],
        ]);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('Renamed', File::findOne($file->id)->name);
    }

    public function testAFileThatIsNotThereIsNotFound(): void
    {
        $this->login();

        $this->expectException(NotFoundHttpException::class);
        Yii::$app->runAction('admin/media/file/update', ['id' => 99999]);
    }

    public function testTheFileAdminIsForbiddenWithoutThePermission(): void
    {
        Yii::$app->getUser()->setIdentity($this->getUserFromFixture('admin'));

        $this->expectException(ForbiddenHttpException::class);
        Yii::$app->runAction('admin/media/file/index');
    }

    public function testAFileIsDuplicatedWithItsContent(): void
    {
        $this->login();
        $file = $this->createFile('photo');

        $response = $this->post('admin/media/file/duplicate', ['id' => $file->id]);

        self::assertInstanceOf(Response::class, $response);

        $duplicate = File::find()
            ->andWhere(['!=', 'id', $file->id])
            ->andWhere(['name' => $file->name])
            ->one();

        self::assertNotNull($duplicate);
        self::assertNotSame($file->basename, $duplicate->basename);
        self::assertFileExists($duplicate->getFilePath());
    }

    public function testAFileIsDeleted(): void
    {
        $this->login();
        $file = $this->createFile('photo');
        $path = $file->getFilePath();

        $response = $this->post('admin/media/file/delete', ['id' => $file->id]);

        self::assertInstanceOf(Response::class, $response);
        self::assertNull(File::findOne($file->id));
        self::assertFileDoesNotExist($path);
    }

    public function testTheFileDeleteRefusesAGetRequest(): void
    {
        $this->login();
        $file = $this->createFile('photo');

        $this->expectException(MethodNotAllowedHttpException::class);
        Yii::$app->runAction('admin/media/file/delete', ['id' => $file->id]);
    }

    public function testTheTransformationsOfAFileAreListed(): void
    {
        $this->login();

        $file = $this->createFile('photo', 100, 100);
        $this->createTransformation($file);

        $html = Yii::$app->runAction('admin/media/transformation/index', ['file' => $file->id]);

        self::assertIsString($html);
        self::assertStringContainsString('square', $html);
    }

    public function testATransformationIsDeleted(): void
    {
        $this->login();

        $file = $this->createFile('photo', 100, 100);
        $transformation = $this->createTransformation($file);

        $response = $this->post('admin/media/transformation/delete', ['id' => $transformation->id]);

        self::assertInstanceOf(Response::class, $response);
        self::assertNull(FileTransformation::findOne($transformation->id));
        self::assertSame(0, File::findOne($file->id)->transformation_count);
    }

    public function testATransformationThatIsNotThereIsNotFound(): void
    {
        $this->login();

        $this->expectException(NotFoundHttpException::class);
        $this->post('admin/media/transformation/delete', ['id' => 99999]);
    }

    /**
     * The grid itself is covered where a real asset model exists — `TestAssetModel` has no table, so the index view
     * cannot eager load it here.
     */
    public function testTheAssetIndexNeedsAFile(): void
    {
        $this->login();

        $this->expectException(NotFoundHttpException::class);
        Yii::$app->runAction('admin/media/asset/index');
    }

    /**
     * Removing an asset from the file page stays on the file page, unless it was the last one.
     */
    public function testAnAssetIsDeletedAndTheUserStaysWhereTheyWere(): void
    {
        $this->login();

        $file = $this->createFile('photo');
        $first = $this->createAsset($file);
        $this->createAsset($file);

        $response = $this->post('admin/media/asset/delete', ['id' => $first->id]);

        self::assertInstanceOf(Response::class, $response);
        self::assertNull(Asset::findOne($first->id));
        self::assertStringContainsString('asset/index', (string)$response->getHeaders()->get('location'));
    }

    public function testTheLastAssetSendsTheUserBackToTheFile(): void
    {
        $this->login();

        $file = $this->createFile('photo');
        $asset = $this->createAsset($file);

        $response = $this->post('admin/media/asset/delete', ['id' => $asset->id]);

        self::assertStringContainsString('file/update', (string)$response->getHeaders()->get('location'));
    }

    /**
     * `TestAssetModel` has no table of its own, so the asset names it by class and carries an id of its own making.
     */
    private function createAsset(File $file): TestAsset
    {
        $asset = TestAsset::create();
        $asset->loadDefaultValues();
        $asset->model_class = TestAssetModel::class;
        $asset->model_id = 1;
        $asset->populateFileRelation($file);

        self::assertTrue($asset->insert(), print_r($asset->getErrors(), true));

        return $asset;
    }

    private function createTransformation(File $file): FileTransformation
    {
        $transformation = FileTransformation::create();
        $transformation->name = 'square';
        $transformation->populateFileRelation($file);

        self::assertTrue($transformation->insert(), print_r($transformation->getErrors(), true));

        return $transformation;
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

    private function createFile(string $basename, int $width = 1000, int $height = 1000): File
    {
        $path = $this->folder->getUploadPath() . "$basename.jpg";

        $image = imagecreatetruecolor($width, $height);
        imagejpeg($image, $path);
        imagedestroy($image);

        $file = File::create();
        $file->loadDefaultValues();
        $file->name = ucfirst($basename);
        $file->basename = $basename;
        $file->extension = 'jpg';
        $file->width = $width;
        $file->height = $height;
        $file->size = filesize($path);
        $file->populateFolderRelation($this->folder);

        self::assertTrue($file->insert(), print_r($file->getErrors(), true));

        return $file;
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
