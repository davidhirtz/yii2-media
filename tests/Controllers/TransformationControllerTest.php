<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Controllers;

use Hirtz\Media\Models\File;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Skeleton\Helpers\FileHelper;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class TransformationControllerTest extends TestCase
{
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

    #[DataProvider('traversingPathProvider')]
    public function testAPathLeavingTheUploadFolderIsNotFound(string $path): void
    {
        $this->expectException(NotFoundHttpException::class);
        Yii::$app->runAction('media/transformation/create', ['path' => $path]);
    }
}
