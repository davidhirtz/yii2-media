<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Setup;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Folder;
use Hirtz\Media\Setup\AssetSeederTrait;
use Hirtz\Media\Test\Models\TestAssetModel;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFileTrait;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Skeleton\Db\ActiveQuery;
use Hirtz\Skeleton\Helpers\FileHelper;
use Hirtz\Skeleton\Setup\Seeder;
use Hirtz\Skeleton\Upload\Upload;
use Hirtz\Skeleton\Web\StreamUploadedFile;
use Override;
use Yii;

class AssetSeederTraitTest extends TestCase
{
    use MediaFileTrait;
    use MediaFixtureTrait;

    private string $source;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $folder = Folder::findOne(1) ?? self::fail('No folder');
        FileHelper::createDirectory($folder->getUploadPath());

        $this->source = Upload::getComponent()->tempPath . '/Rösti 東京 🍽.jpg';
        FileHelper::createDirectory(dirname($this->source));
        $this->writeImage($this->source, 120, 80);

        AssetSeederStreamUploadedFile::$source = $this->source;
        Yii::$container->set(StreamUploadedFile::class, AssetSeederStreamUploadedFile::class);
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
            ->createTable(AssetSeederModel::tableName(), [
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
            ->dropTable(AssetSeederModel::tableName())
            ->execute();
    }

    public function testAFileDeclaredTwiceIsFetchedOnce(): void
    {
        $model = $this->insertModel();
        $other = $this->insertModel();
        $seeder = new AssetSeederTestSeeder();

        $seeder->seed($model, [
            ['url' => 'https://files.example/r%C3%B6sti.jpg', 'alt_text' => 'Rösti Å 東京 🍽'],
            ['path' => $this->source],
        ]);

        $seeder->seed($other, [['url' => 'https://files.example/r%C3%B6sti.jpg']]);

        self::assertSame([], $seeder->getFailed());

        $assets = Asset::find()->where(['model_id' => $model->id])->orderBy(['id' => SORT_ASC])->all();

        self::assertCount(2, $assets);
        self::assertNotSame($assets[0]->file_id, $assets[1]->file_id);
        self::assertSame('Rösti Å 東京 🍽', $assets[0]->getAttribute('alt_text'));
        self::assertSame(120, $assets[0]->file?->width);
        self::assertSame($assets[0]->file_id, Asset::findOne(['model_id' => $other->id])?->file_id);
    }

    public function testAFileThatCannotBeReadCostsOnlyItsAsset(): void
    {
        $model = $this->insertModel();
        $count = (int)File::find()->count();
        $seeder = new AssetSeederTestSeeder();

        $seeder->seed($model, [
            ['url' => 'http://127.0.0.1/secret.jpg'],
            ['path' => '@runtime/missing.jpg'],
            ['url' => 'https://files.example/a.jpg', 'path' => $this->source],
            ['url' => 'https://files.example/a.jpg'],
        ]);

        $errors = array_map(fn ($record): string => implode(' ', $record->getErrorSummary(true)), $seeder->getFailed());

        self::assertCount(3, $errors);
        self::assertStringContainsString('http://127.0.0.1/secret.jpg could not be read.', $errors[0]);
        self::assertStringContainsString('@runtime/missing.jpg could not be read.', $errors[1]);
        self::assertStringContainsString('either the url or the path', $errors[2]);
        self::assertSame($count + 1, (int)File::find()->count());
        self::assertSame(1, (int)Asset::find()->where(['model_id' => $model->id])->count());
    }

    private function insertModel(): AssetSeederModel
    {
        $model = AssetSeederModel::create();
        $model->loadDefaultValues();

        self::assertTrue($model->insert(false));
        return $model;
    }
}

class AssetSeederTestSeeder extends Seeder
{
    use AssetSeederTrait;

    #[Override]
    public function run(?array $value): void
    {
    }

    /**
     * @param array<array-key, mixed> $assets
     */
    public function seed(AssetSeederModel $model, array $assets): void
    {
        $this->insertAssets($model, $assets);
    }
}

class AssetSeederModel extends TestAssetModel
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
        return 'setup_asset_seeder_model';
    }
}

/**
 * Serves a local file in place of the URL, so no request leaves the test.
 */
class AssetSeederStreamUploadedFile extends StreamUploadedFile
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
