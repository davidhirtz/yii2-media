<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Mcp;

use Hirtz\Media\Mcp\AttachFileTool;
use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\Models\TestAssetModel;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Skeleton\Db\ActiveQuery;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Mcp\Mcp;
use Hirtz\Skeleton\Mcp\Resource;
use Hirtz\Skeleton\Mcp\Tools\ToolInterface;
use Hirtz\Skeleton\Models\User;
use Hirtz\Skeleton\Test\Fixtures\UserFixture;
use Hirtz\Skeleton\Upload\Upload;
use Hirtz\Skeleton\Web\StreamUploadedFile;
use Override;
use Yii;

class AttachFileToolTest extends TestCase
{
    use MediaFileTrait;
    use MediaFixtureTrait {
        fixtures as mediaFixtures;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function fixtures(): array
    {
        return [
            ...$this->mediaFixtures(),
            'user' => UserFixture::class,
        ];
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = Folder::findOne(1) ?? self::fail('No folder');
        FileHelper::createDirectory($this->folder->getUploadPath());

        Mcp::getComponent()->resources = [
            fn (): Resource => Resource::make('model', AttachFileModel::class),
        ];

        $this->getWebUser()->setIdentity(User::find()->andWhere(['is_owner' => true])->one() ?? self::fail('No owner'));
    }

    #[Override]
    protected function tearDown(): void
    {
        Yii::$container->clear(StreamUploadedFile::class);
        FileHelper::removeDirectory((string)File::getModule()->uploadPath);
        FileHelper::removeDirectory(Upload::getComponent()->tempPath);

        parent::tearDown();
    }

    #[Override]
    protected function setUpSchema(): void
    {
        Yii::$app->getDb()->createCommand()
            ->createTable(AttachFileModel::tableName(), [
                'id' => 'pk',
                'type' => 'integer not null default 1',
                'asset_count' => 'integer not null default 0',
                'updated_at' => 'datetime null',
            ])
            ->execute();
    }

    #[Override]
    protected function tearDownSchema(): void
    {
        Yii::$app->getDb()->createCommand()
            ->dropTable(AttachFileModel::tableName())
            ->execute();
    }

    public function testAFileIsFetchedAndAttached(): void
    {
        $source = Upload::getComponent()->tempPath . '/Rösti 東京.jpg';
        FileHelper::createDirectory(dirname($source));
        $this->writeImage($source, 120, 80);

        LocalStreamUploadedFile::$source = $source;
        Yii::$container->set(StreamUploadedFile::class, LocalStreamUploadedFile::class);

        $model = $this->insertModel();
        $result = $this->attach(['resource' => 'model', 'id' => $model->id, 'url' => 'https://files.example/R%C3%B6sti.jpg']);

        self::assertFalse($result['isError'], json_encode($result['structuredContent'], JSON_UNESCAPED_UNICODE) ?: '');

        $file = File::findOne($result['structuredContent']['file']['id'] ?? 0) ?? self::fail('No file');

        self::assertSame($this->folder->id, $file->folder_id);
        self::assertSame(120, $file->width);
        self::assertFileExists($file->getFilePath());

        $asset = Asset::findOne($result['structuredContent']['asset'] ?? 0);

        self::assertSame($file->id, $asset?->file_id);
        self::assertSame((string)$model->id, (string)$asset->model_id);
    }

    public function testAPrivateAddressIsRefused(): void
    {
        $model = $this->insertModel();
        $count = (int)File::find()->count();

        $result = $this->attach(['resource' => 'model', 'id' => $model->id, 'url' => 'http://127.0.0.1/secret.jpg']);

        self::assertTrue($result['isError']);
        self::assertSame('The file was not saved.', $result['structuredContent']['error'] ?? null);
        self::assertSame($count, (int)File::find()->count());
    }

    public function testWithoutStreamUploadsOnlyContentIsTaken(): void
    {
        Upload::getComponent()->enableStreamUploads = false;

        $names = array_map(fn (ToolInterface $tool): string => $tool->getName(), Mcp::getComponent()->getTools());
        self::assertContains('attach_file', $names);

        $result = $this->attach(['resource' => 'model', 'id' => $this->insertModel()->id, 'url' => 'https://files.example/a.jpg']);
        self::assertSame('Files cannot be fetched from a URL here, pass "content".', $result['structuredContent']['error'] ?? null);
    }

    public function testContentIsWrittenAsTheFileItNames(): void
    {
        $source = Upload::getComponent()->tempPath . '/source.jpg';
        FileHelper::createDirectory(dirname($source));
        $this->writeImage($source, 60, 40);

        $model = $this->insertModel();
        $result = $this->attach([
            'resource' => 'model',
            'id' => $model->id,
            'content' => 'data:image/jpeg;base64,' . base64_encode((string)file_get_contents($source)),
            'filename' => '../../Grüße 🎉.jpg',
        ]);

        self::assertFalse($result['isError'], json_encode($result['structuredContent'], JSON_UNESCAPED_UNICODE) ?: '');

        $file = File::findOne($result['structuredContent']['file']['id'] ?? 0) ?? self::fail('No file');
        self::assertSame(60, $file->width);
        self::assertStringNotContainsString('..', $file->basename);
        self::assertFileExists($file->getFilePath());
    }

    public function testContentNeedsItsFilenameAndExcludesAUrl(): void
    {
        $model = $this->insertModel();

        foreach ([
            '"content" needs its "filename".' => ['content' => base64_encode('x')],
            'Pass either "url" or "content".' => ['content' => base64_encode('x'), 'filename' => 'a.jpg', 'url' => 'https://files.example/a.jpg'],
            '"content" is not base64.' => ['content' => '!!!', 'filename' => 'a.jpg'],
        ] as $error => $arguments) {
            $result = $this->attach(['resource' => 'model', 'id' => $model->id, ...$arguments]);
            self::assertSame($error, $result['structuredContent']['error'] ?? null);
        }
    }

    private function insertModel(): AttachFileModel
    {
        $model = AttachFileModel::create();
        $model->loadDefaultValues();

        self::assertTrue($model->insert(false));
        return $model;
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{isError: bool, structuredContent: array<string, mixed>}
     */
    private function attach(array $arguments): array
    {
        $result = (new AttachFileTool(Mcp::getComponent()))->call($arguments);
        return ['isError' => $result->isError, 'structuredContent' => $result->structuredContent];
    }
}

/**
 * A model that takes assets on a table of its own, so the tool can find it by id.
 */
class AttachFileModel extends TestAssetModel
{
    /**
     * @return ActiveQuery<static>
     */
    #[Override]
    public static function find(): ActiveQuery
    {
        return Yii::createObject(ActiveQuery::class, [static::class]);
    }

    #[Override]
    public static function tableName(): string
    {
        return 'mcp_attach_file_model';
    }
}

/**
 * Serves a local file in place of the URL, so no request leaves the test.
 */
class LocalStreamUploadedFile extends StreamUploadedFile
{
    public static string $source = '';

    #[Override]
    protected function isAllowedUrl(string $url): bool
    {
        return parse_url($url, PHP_URL_HOST) === 'files.example';
    }

    #[Override]
    protected function openUrl(string $url)
    {
        return $this->isAllowedUrl($url) ? (fopen(self::$source, 'rb') ?: null) : null;
    }
}
