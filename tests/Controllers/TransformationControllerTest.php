<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Controllers;

use Hirtz\Media\Controllers\TransformationController;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\FileTransformation;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\Fixtures\FolderFixture;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Models\Redirect;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Yii;
use yii\base\Event;
use yii\base\ModelEvent;
use yii\db\BaseActiveRecord;
use yii\log\Logger;
use yii\mutex\MysqlMutex;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class TransformationControllerTest extends TestCase
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
        ];
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        File::getModule()->addTransformation(Transformation::make('square')->width(50)->height(50));

        $uploadPath = (string)File::getModule()->uploadPath;

        FileHelper::createDirectory($uploadPath . 'default/square');
        file_put_contents($uploadPath . 'default/square/photo.jpg', 'transformed');
        file_put_contents($uploadPath . 'default/square/script.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        file_put_contents($uploadPath . 'default/script.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        file_put_contents($uploadPath . 'default/public.txt', 'public');
        file_put_contents(dirname($uploadPath) . '/secret.txt', 'secret');
    }

    #[Override]
    protected function tearDown(): void
    {
        $uploadPath = (string)File::getModule()->uploadPath;

        FileHelper::removeDirectory($uploadPath);
        FileHelper::unlink(dirname($uploadPath) . '/secret.txt');

        parent::tearDown();
    }

    public function testAnExistingTransformationIsSent(): void
    {
        $response = Yii::$app->runAction('media/transformation/create', ['path' => 'default/square/photo.jpg']);

        self::assertInstanceOf(Response::class, $response);
        self::assertIsArray($response->stream);
        // Yii measured the file by seeking to its end; the response rewinds when it is sent.
        self::assertSame('transformed', stream_get_contents($response->stream[0], offset: 0));
    }

    /**
     * The file's rename wrote one redirect for its own URL, which every derivative follows.
     */
    #[DataProvider('provideMovedFileExtensions')]
    public function testADerivativeOfAMovedFileFollowsTheFilesRedirect(string $extension): void
    {
        $baseUrl = File::getModule()->baseUrl;

        $redirect = Redirect::create();
        $redirect->request_uri = "{$baseUrl}default/old-name.jpg";
        $redirect->url = "{$baseUrl}archive/ab12cd34/Mu\u{308}ller.jpg";
        self::assertTrue($redirect->insert(), print_r($redirect->getErrors(), true));

        $response = Yii::$app->runAction('media/transformation/create', ['path' => "default/square/old-name.$extension"]);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(301, $response->getStatusCode());
        self::assertStringEndsWith("{$baseUrl}archive/square/ab12cd34/Mu\u{308}ller.$extension", (string)$response->getHeaders()->get('location'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideMovedFileExtensions(): array
    {
        return ['same format' => ['jpg'], 'another format' => ['webp']];
    }

    public function testADerivativeOfAnUnknownFileIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        Yii::$app->runAction('media/transformation/create', ['path' => 'default/square/never-there.jpg']);
    }

    public function testATransformationIsSentWithoutSniffing(): void
    {
        $response = Yii::$app->runAction('media/transformation/create', ['path' => 'default/square/photo.jpg']);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('nosniff', $response->getHeaders()->get('X-Content-Type-Options'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonTransformationPathProvider(): array
    {
        return [
            'original' => ['default/public.txt'],
            'svg' => ['default/script.svg'],
            'svg named like a transformation' => ['default/square/script.svg'],
            'unknown transformation' => ['default/unknown/photo.jpg'],
        ];
    }

    /**
     * @see https://github.com/davidhirtz/yii2-monorepo/issues/388
     */
    #[DataProvider('nonTransformationPathProvider')]
    public function testAnExistingFileThatIsNoTransformationIsNotFound(string $path): void
    {
        $this->expectException(NotFoundHttpException::class);
        Yii::$app->runAction('media/transformation/create', ['path' => $path]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function traversingPathProvider(): array
    {
        return [
            'parent' => ['../secret.txt'],
            'nested' => ['default/../../secret.txt'],
            'backslash' => ['default\\..\\..\\secret.txt'],
            'null byte' => ["../secret.txt\0.jpg"],
        ];
    }

    /**
     * @see https://github.com/davidhirtz/yii2-monorepo/issues/471
     */
    public function testAConcurrentRequestThatCreatedTheTransformationFirstIsNoError(): void
    {
        $this->folder = Folder::findOne(1);
        $file = $this->createFile('race');

        // The other request inserts its row after this one has passed validation
        Event::on(FileTransformation::class, BaseActiveRecord::EVENT_BEFORE_INSERT, function (ModelEvent $event): void {
            $transformation = $event->sender;
            self::assertInstanceOf(FileTransformation::class, $transformation);

            Yii::$app->getDb()->createCommand()->insert(FileTransformation::tableName(), [
                'file_id' => $transformation->file_id,
                'name' => $transformation->name,
                'extension' => $transformation->extension,
                'width' => 50,
                'height' => 50,
                'size' => 1,
                'created_at' => '2026-10-04 00:00:00',
            ])->execute();
        });

        $this->logger->isRecording = true;

        $response = Yii::$app->runAction('media/transformation/create', ['path' => 'default/square/race.jpg']);

        $this->logger->isRecording = false;

        self::assertInstanceOf(Response::class, $response);
        self::assertIsArray($response->stream);
        self::assertSame([], array_filter($this->logger->messages, fn (array $message): bool => $message[1] === Logger::LEVEL_ERROR));
        self::assertSame(1, (int)FileTransformation::find()->where(['file_id' => $file->id])->count());
    }

    /**
     * A request finding another one writing the same derivative waits for it, and sends the original when the wait
     * runs out rather than processing the image a second time.
     */
    /**
     * Every image of a page asks for its transformations, so a broken original logged an error on each request (#694).
     */
    public function testAnOriginalThatFailsAnswersAPlaceholderAndLogsOnce(): void
    {
        $this->folder = Folder::findOne(1);
        $file = $this->createFile('broken');
        file_put_contents($file->getFilePath(), "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 64));

        $this->logger->isRecording = true;
        $this->logger->messages = [];

        foreach ([1, 2] as $request) {
            $controller = Yii::$app->createController('media/transformation')[0] ?? self::fail('No controller');
            self::assertInstanceOf(TransformationController::class, $controller);

            $response = $controller->runAction('create', ['path' => 'default/square/broken.jpg']);

            self::assertInstanceOf(Response::class, $response);
            self::assertSame('image/svg+xml', $response->getHeaders()->get('content-type'), "Request $request");
            self::assertStringStartsWith('<svg', (string)$response->content);
        }

        $errors = array_filter($this->logger->messages, fn (array $message): bool => $message[1] === Logger::LEVEL_ERROR);
        self::assertCount(1, $errors);
        self::assertSame(0, (int)FileTransformation::find()->where(['file_id' => $file->id])->count());

        // The file's admin page says why, until the file is replaced or edited
        self::assertNotNull($file->getTransformationFailure());

        $file->deleteTransformations();
        self::assertNull($file->getTransformationFailure());
    }

    public function testARequestThatCannotLockTheTransformationRedirectsToTheOriginal(): void
    {
        $this->folder = Folder::findOne(1);
        $file = $this->createFile('locked');

        $transformation = FileTransformation::create();
        $transformation->name = 'square';
        $transformation->extension = 'jpg';
        $transformation->populateFileRelation($file);

        // A lock is held per connection, so the other request needs one of its own
        $db = clone Yii::$app->getDb();
        $mutex = new MysqlMutex(['db' => $db]);
        $lockName = 'transformation-' . $transformation->getFilePath();

        self::assertTrue($mutex->acquire($lockName));

        try {
            $controller = Yii::$app->createController('media/transformation')[0] ?? self::fail('No controller');
            self::assertInstanceOf(TransformationController::class, $controller);
            $controller->lockTimeout = 0;

            $response = $controller->runAction('create', ['path' => 'default/square/locked.jpg']);
        } finally {
            $mutex->release($lockName);
            $db->close();
        }

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame(0, (int)FileTransformation::find()->where(['file_id' => $file->id])->count());
    }

    #[DataProvider('traversingPathProvider')]
    public function testAPathLeavingTheUploadFolderIsNotFound(string $path): void
    {
        $this->expectException(NotFoundHttpException::class);
        Yii::$app->runAction('media/transformation/create', ['path' => $path]);
    }
}
