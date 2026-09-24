<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Modules\Admin\Controllers;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Models\FileTransformation;
use Hirtz\Media\Modules\Admin\Widgets\Navs\AssetActionDropdown;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Media\Test\Fixtures\FileFixture;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\Models\TestAssetModel;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Helpers\Html;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Models\User;
use Hirtz\Skeleton\Test\Fixtures\UserFixture;
use Hirtz\Skeleton\Web\Controller;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->getWebUser()->setIdentity($this->getUserFromFixture('admin'));

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
            ->andWhere(['name' => "Copy of $file->name"])
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

    public function testAFileDeletedFromElsewhereReturnsThere(): void
    {
        $this->login();
        $file = $this->createFile('photo');

        $response = $this->post('admin/media/file/delete', [
            'id' => $file->id,
            'returnUrl' => '/admin/test-asset/index?model=1',
        ]);

        self::assertInstanceOf(Response::class, $response);
        self::assertNull(File::findOne($file->id));
        self::assertSame(
            $this->getWebRequest()->getHostInfo() . '/admin/test-asset/index?model=1',
            $response->getHeaders()->get('Location'),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function foreignReturnUrlProvider(): array
    {
        return [
            'absolute' => ['https://example.com/'],
            'protocol relative' => ['//example.com/'],
            'backslash' => ['/\\example.com/'],
            'relative' => ['admin/media/file/index'],
            'control character' => ["/\nexample"],
        ];
    }

    #[DataProvider('foreignReturnUrlProvider')]
    public function testAReturnUrlLeavingTheSiteIsIgnored(string $returnUrl): void
    {
        $this->login();
        $file = $this->createFile('photo');

        $response = $this->post('admin/media/file/delete', ['id' => $file->id, 'returnUrl' => $returnUrl]);

        self::assertInstanceOf(Response::class, $response);

        $location = (string)$response->getHeaders()->get('Location');
        self::assertStringContainsString('/admin/media/file/index', $location);
        self::assertStringNotContainsString('example', $location);
        self::assertStringNotContainsString('returnUrl', $location);
    }

    /**
     * The asset page goes with the file, so its delete button sends the user to the model's asset list.
     */
    public function testTheAssetPageDeletesTheFileBackToTheAssetList(): void
    {
        $this->login();
        $asset = $this->createAsset($this->createFile('photo'));

        // the asset's own buttons name routes relative to the asset controller
        Yii::$app->controller = new Controller('test-asset', Yii::$app);

        $html = (string)AssetActionDropdown::make()->model($asset);
        $url = Url::to(['/admin/media/file/delete', 'id' => $asset->file_id, 'returnUrl' => Url::to($asset::getAdminIndexRoute($asset->model))]);

        self::assertStringContainsString(Html::encode($url), $html);
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

        $file = $this->createFile('photo');
        $this->createTransformation($file);

        $html = Yii::$app->runAction('admin/media/transformation/index', ['file' => $file->id]);

        self::assertIsString($html);
        self::assertStringContainsString('square', $html);
    }

    public function testATransformationIsDeleted(): void
    {
        $this->login();

        $file = $this->createFile('photo');
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

        // one file, two records — a record holds a file once, so the second asset needs a model of its own
        $first = $this->createAsset($file);
        $this->createAsset($file, 2);

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
    private function createAsset(File $file, int $modelId = 1): TestAsset
    {
        $asset = TestAsset::create();
        $asset->loadDefaultValues();
        $asset->model_class = TestAssetModel::class;
        $asset->model_id = $modelId;
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
