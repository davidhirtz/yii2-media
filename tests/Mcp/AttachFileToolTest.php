<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Mcp;

use Hirtz\Media\Mcp\AttachFileTool;
use Hirtz\Media\Mcp\GenerateImageTool;
use Hirtz\Media\Mcp\UploadLinkTool;
use Hirtz\Media\Images\Generators\ImageGeneratorInterface;
use Hirtz\Media\Images\Generators\GeneratedImage;
use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Test\Models\TestAssetModel;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Skeleton\Credentials\Credentials;
use Hirtz\Skeleton\Db\ActiveQuery;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Mcp\Mcp;
use Hirtz\Skeleton\Mcp\Resource;
use Hirtz\Skeleton\Mcp\Tools\GetRecordTool;
use Hirtz\Skeleton\Mcp\Tools\ToolInterface;
use Hirtz\Skeleton\Models\User;
use Hirtz\Skeleton\Models\UserCredential;
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

    public function testSvgContentIsStoredWithoutScripts(): void
    {
        $result = $this->attach([
            'resource' => 'model',
            'id' => $this->insertModel()->id,
            'content' => base64_encode((string)file_get_contents(__DIR__ . '/../Data/malicious.svg')),
            'filename' => 'Grüße 漢字 🎉.svg',
        ]);

        self::assertFalse($result['isError'], json_encode($result['structuredContent'], JSON_UNESCAPED_UNICODE) ?: '');

        $file = File::findOne($result['structuredContent']['file']['id'] ?? 0) ?? self::fail('No file');
        $svg = (string)file_get_contents($file->getFilePath());

        self::assertSame(120, $file->width);
        self::assertStringContainsString('漢字', $svg);

        foreach (['<script', 'onload', 'onclick', 'foreignObject', 'javascript:', 'evil.example'] as $needle) {
            self::assertStringNotContainsStringIgnoringCase($needle, $svg);
        }
    }

    public function testAnImageIsShownWithAPreview(): void
    {
        $source = Upload::getComponent()->tempPath . '/wide.jpg';
        FileHelper::createDirectory(dirname($source));
        $this->writeImage($source, 1600, 800);

        $result = $this->attach([
            'resource' => 'model',
            'id' => $this->insertModel()->id,
            'content' => base64_encode((string)file_get_contents($source)),
            'filename' => 'wide.jpg',
        ]);

        $id = $result['structuredContent']['file']['id'] ?? self::fail('No file');

        // the bundle's own `file` resource, which the setup replaced
        $this->reloadApplication();
        $this->getWebUser()->setIdentity(User::find()->andWhere(['is_owner' => true])->one() ?? self::fail('No owner'));

        $content = (new GetRecordTool(Mcp::getComponent()))->call(['resource' => 'file', 'id' => $id])->toArray()['content'];

        self::assertSame('image', $content[1]['type'] ?? null);
        self::assertSame('image/jpeg', $content[1]['mimeType'] ?? null);

        $size = getimagesizefromstring((string)base64_decode((string)($content[1]['data'] ?? ''), true));
        self::assertSame([1024, 512], [$size[0] ?? null, $size[1] ?? null]);

        File::getModule()->mcpPreviewSize = null;
        self::assertCount(1, (new GetRecordTool(Mcp::getComponent()))->call(['resource' => 'file', 'id' => $id])->toArray()['content']);
    }

    public function testAnImageIsGeneratedOnTheSitesKeyAsADraftWithinTheDailyLimit(): void
    {
        $module = File::getModule();
        $module->imageGenerators = ['fake' => $this->createGenerator('site-key')];
        $module->imageGeneratorDailyLimit = 1;

        self::assertContains('generate_image', $this->getToolNames());

        $model = $this->insertModel();
        $result = $this->generate(['resource' => 'model', 'id' => $model->id, 'prompt' => 'Ein Sommerfest am See – Привет 🎉', 'aspect' => 'square']);

        self::assertFalse($result['isError'], json_encode($result['structuredContent'], JSON_UNESCAPED_UNICODE) ?: '');
        self::assertSame('the site', $result['structuredContent']['billed_to'] ?? null);
        self::assertSame(['site-key', 'square'], FakeImageGenerator::$calls[0] ?? null);

        $file = File::findOne($result['structuredContent']['file']['id'] ?? 0) ?? self::fail('No file');
        self::assertFileExists($file->getFilePath());
        self::assertSame(Asset::STATUS_DRAFT, Asset::findOne($result['structuredContent']['asset'] ?? 0)?->status);

        $result = $this->generate(['resource' => 'model', 'id' => $model->id, 'prompt' => 'Noch eins']);
        self::assertSame('The daily limit of 1 images is reached.', $result['structuredContent']['error'] ?? null);
        self::assertCount(1, FakeImageGenerator::$calls);
    }

    public function testAUsersOwnKeyIsPreferredAndRequiredWhenTheSiteHasNone(): void
    {
        $module = File::getModule();
        $module->imageGenerators = ['fake' => $this->createGenerator(null)];
        $module->imageGeneratorKeys = 'user';

        self::assertNotContains('generate_image', $this->getToolNames());
        self::assertNotNull(Credentials::getComponent()->getProvider('fake'));

        $owner = User::find()->andWhere(['is_owner' => true])->one() ?? self::fail('No owner');
        UserCredential::store($owner->id, 'fake', 'own-key');

        self::assertContains('generate_image', $this->getToolNames());

        $result = $this->generate(['resource' => 'model', 'id' => $this->insertModel()->id, 'prompt' => 'Ein Leuchtturm']);

        self::assertSame('your own key', $result['structuredContent']['billed_to'] ?? null);
        self::assertSame(['own-key', 'landscape'], FakeImageGenerator::$calls[0] ?? null);
    }

    public function testFourServicesAreTheDefaultEachOfferedOnceItHasAKey(): void
    {
        self::assertSame(['openai', 'google', 'stability', 'firefly'], array_keys(File::getModule()->getImageGenerators()));
        self::assertNotContains('generate_image', $this->getToolNames());

        Yii::$app->params['stabilityApiKey'] = 'sk-site';
        self::assertContains('generate_image', $this->getToolNames());

        $schema = (new GenerateImageTool(Mcp::getComponent()))->getInputSchema();
        self::assertSame(['stability'], $schema['properties']['provider']['enum'] ?? null);

        File::getModule()->imageGenerators = ['stability' => null];
        self::assertNotContains('generate_image', $this->getToolNames());
    }

    public function testTheUserChoosesAmongTheServicesTheyHaveAKeyFor(): void
    {
        $module = File::getModule();
        $module->imageGenerators = [
            'fake' => $this->createGenerator(null),
            'other' => new class () extends FakeImageGenerator {
                #[Override]
                public function getName(): string
                {
                    return 'other';
                }
            },
        ];

        $owner = User::find()->andWhere(['is_owner' => true])->one() ?? self::fail('No owner');
        UserCredential::store($owner->id, 'other', 'own-other-key');

        $tool = new GenerateImageTool(Mcp::getComponent());
        self::assertSame(['other'], $tool->getInputSchema()['properties']['provider']['enum'] ?? null);

        $result = $this->generate(['resource' => 'model', 'id' => $this->insertModel()->id, 'prompt' => 'Ein Leuchtturm', 'provider' => 'fake']);
        self::assertStringContainsString('No key for this image service', (string)($result['structuredContent']['error'] ?? ''));

        $result = $this->generate(['resource' => 'model', 'id' => $this->insertModel()->id, 'prompt' => 'Ein Leuchtturm']);
        self::assertSame('your own key', $result['structuredContent']['billed_to'] ?? null);
        self::assertSame(['own-other-key', 'landscape'], FakeImageGenerator::$calls[0] ?? null);
    }

    public function testByDefaultAUserMayAddAKeyOfTheirOwn(): void
    {
        File::getModule()->imageGenerators = ['fake' => $this->createGenerator('site-key')];
        self::assertNotNull(Credentials::getComponent()->getProvider('fake'));
    }

    public function testWithTheSitesKeyOnlyNoUserKeyIsOffered(): void
    {
        File::getModule()->imageGenerators = ['fake' => $this->createGenerator('site-key')];
        File::getModule()->imageGeneratorKeys = 'site';

        self::assertNull(Credentials::getComponent()->getProvider('fake'));
    }

    public function testUploadLinkNamesTheFolderPageOrTheRecordsAssets(): void
    {
        // the tool comes with the resource the agent finds the uploads through
        Mcp::getComponent()->resources[] = fn (): Resource => Resource::make('file', File::class)->creatable(false);
        $tool = new UploadLinkTool(Mcp::getComponent());

        $result = $tool->call(['folder' => $this->folder->id])->structuredContent;
        self::assertSame("https://www.test.localhost/admin/media/file/index?folder={$this->folder->id}", $result['url'] ?? null);

        $result = $tool->call(['folder' => 999])->structuredContent;
        self::assertSame('Folder not found: 999', $result['error'] ?? null);

        $model = $this->insertModel();
        $result = $tool->call(['resource' => 'model', 'id' => $model->id]);
        self::assertFalse($result->isError, json_encode($result->structuredContent) ?: '');
        self::assertStringContainsString((string)$model->id, (string)($result->structuredContent['url'] ?? ''));
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

    private function createGenerator(?string $apiKey): FakeImageGenerator
    {
        $source = Upload::getComponent()->tempPath . '/generated.jpg';
        $this->writeImage($source, 64, 64);

        FakeImageGenerator::$content = (string)file_get_contents($source);
        FakeImageGenerator::$calls = [];

        $generator = new FakeImageGenerator();
        $generator->apiKey = $apiKey;

        return $generator;
    }

    /**
     * @return list<string>
     */
    private function getToolNames(): array
    {
        return array_map(fn (ToolInterface $tool): string => $tool->getName(), Mcp::getComponent()->getTools());
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{isError: bool, structuredContent: array<string, mixed>}
     */
    private function generate(array $arguments): array
    {
        $result = (new GenerateImageTool(Mcp::getComponent()))->call($arguments);
        return ['isError' => $result->isError, 'structuredContent' => $result->structuredContent];
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

class FakeImageGenerator implements ImageGeneratorInterface
{
    public static string $content = '';

    /**
     * @var list<array{string, string}>
     */
    public static array $calls = [];

    public ?string $apiKey = null;

    #[Override]
    public function getName(): string
    {
        return 'fake';
    }

    #[Override]
    public function getLabel(): string
    {
        return 'Fäke Bilder';
    }

    #[Override]
    public function getDescription(): string
    {
        return 'Fäkes images for tests.';
    }

    #[Override]
    public function getKeyUrl(): ?string
    {
        return null;
    }

    #[Override]
    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    #[Override]
    public function generate(string $prompt, string $aspect, string $apiKey): GeneratedImage
    {
        self::$calls[] = [$apiKey, $aspect];
        return new GeneratedImage(self::$content, 'jpg');
    }

    #[Override]
    public function verify(string $apiKey): bool
    {
        return true;
    }
}
