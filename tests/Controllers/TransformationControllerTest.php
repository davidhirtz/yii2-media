<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Controllers;

use Hirtz\Media\Models\File;
use Hirtz\Media\Test\TestCase;
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

        $uploadPath = (string)File::getModule()->uploadPath;

        FileHelper::createDirectory($uploadPath . 'default');
        file_put_contents($uploadPath . 'default/public.txt', 'public');
        file_put_contents($uploadPath . 'default/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        file_put_contents(dirname($uploadPath) . '/secret.txt', 'secret');
    }

    public function testAFileInTheUploadFolderIsSent(): void
    {
        $response = Yii::$app->runAction('media/transformation/create', ['path' => 'default/public.txt']);

        self::assertInstanceOf(Response::class, $response);
        self::assertIsArray($response->stream);
        // Yii measured the file by seeking to its end; the response rewinds when it is sent.
        self::assertSame('public', stream_get_contents($response->stream[0], offset: 0));
    }

    public function testAFileIsSentWithoutSniffingAndAnSvgWithoutScripts(): void
    {
        $response = Yii::$app->runAction('media/transformation/create', ['path' => 'default/public.txt']);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('nosniff', $response->getHeaders()->get('X-Content-Type-Options'));
        self::assertStringNotContainsString('sandbox', (string)$response->getHeaders()->get('Content-Security-Policy'));

        $response = Yii::$app->runAction('media/transformation/create', ['path' => 'default/logo.svg']);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(
            "frame-ancestors 'self'; script-src 'none'; sandbox",
            $response->getHeaders()->get('Content-Security-Policy'),
        );
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
