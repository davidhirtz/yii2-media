<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\CustomAttributes\AltTextCustomAttribute;
use Hirtz\Media\Models\CustomAttributes\EmbedUrlCustomAttribute;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\Models\TestAssetModel;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Test\Traits\MediaFixtureTrait;
use Hirtz\Media\Models\File;
use Hirtz\Skeleton\Models\CustomAttributes\HtmlCustomAttribute;
use Hirtz\Skeleton\Models\CustomAttributes\SelectCustomAttribute;
use Hirtz\Skeleton\Models\CustomAttributes\TextCustomAttribute;
use Hirtz\Skeleton\Models\CustomAttributes\UrlCustomAttribute;
use Yii;

class AssetTest extends TestCase
{
    use MediaFixtureTrait;

    public function testInstantiatePicksTheRegisteredSubclass(): void
    {
        $asset = Asset::instantiate(['model_class' => TestAssetModel::class]);
        self::assertInstanceOf(TestAsset::class, $asset);
    }

    public function testInstantiateFallsBackToTheBase(): void
    {
        $asset = Asset::instantiate(['model_class' => self::class]);

        self::assertInstanceOf(Asset::class, $asset);
        self::assertNotInstanceOf(TestAsset::class, $asset);
    }

    public function testTheSubclassQueryIsScoped(): void
    {
        self::assertSame(
            ['{{%asset}}.[[model_class]]' => TestAssetModel::class],
            TestAsset::find()->where,
        );

        self::assertNull(Asset::find()->where);
    }

    public function testWhereModelsGroupsByClass(): void
    {
        $condition = Asset::find()
            ->whereModels([$this->createModel(1), $this->createModel(2)])
            ->where;

        self::assertSame([
            'or',
            [
                '{{%asset}}.[[model_class]]' => TestAssetModel::class,
                '{{%asset}}.[[model_id]]' => [1, 2],
            ],
        ], $condition);
    }

    public function testWhereModelsWithoutModelsMatchesNothing(): void
    {
        self::assertSame(0, (int)Asset::find()->whereModels([])->count());
    }

    public function testIsModel(): void
    {
        $asset = TestAsset::create();
        $asset->model_class = TestAssetModel::class;

        self::assertTrue($asset->isModel(TestAssetModel::class));
        self::assertFalse($asset->isModel(self::class));
    }

    public function testTheModelIsImmutable(): void
    {
        $asset = $this->createAsset();
        self::assertTrue($asset->insert());

        $asset->model_id = 99;
        self::assertFalse($asset->validate());
        self::assertArrayHasKey('model_id', $asset->getErrors());
    }

    public function testTheDefaultDefinitionsExistOnABareInstance(): void
    {
        $definitions = Asset::instance()->getCustomAttributeDefinitions();

        self::assertInstanceOf(TextCustomAttribute::class, $definitions['name']);
        self::assertInstanceOf(HtmlCustomAttribute::class, $definitions['content']);
        self::assertInstanceOf(AltTextCustomAttribute::class, $definitions['alt_text']);
        self::assertInstanceOf(UrlCustomAttribute::class, $definitions['link']);
        self::assertInstanceOf(EmbedUrlCustomAttribute::class, $definitions['embed_url']);
        self::assertInstanceOf(SelectCustomAttribute::class, $definitions['loading']);
        self::assertInstanceOf(SelectCustomAttribute::class, $definitions['fetchpriority']);
    }

    public function testLoadingAndFetchPriorityAreOnlyOfferedForAPreviewableFile(): void
    {
        $definitions = Asset::instance()->getCustomAttributeDefinitions();

        $asset = TestAsset::create();
        $asset->populateFileRelation($this->getFileFromFixture('file-1'));

        self::assertTrue($definitions['loading']->isVisible($asset));
        self::assertTrue($definitions['fetchpriority']->isVisible($asset));

        $video = File::create();
        $video->extension = 'mp4';

        $asset = TestAsset::create();
        $asset->populateFileRelation($video);

        self::assertFalse($definitions['loading']->isVisible($asset));
        self::assertFalse($definitions['fetchpriority']->isVisible($asset));
    }

    public function testLoadingAndFetchPriorityDefaultToNullAndAreRangeChecked(): void
    {
        $asset = $this->createAsset();

        self::assertNull($asset->getLoading());
        self::assertNull($asset->getFetchPriority());

        $asset->setAttributes([
            'fetchpriority' => 'high',
            'loading' => 'sometimes',
        ]);

        self::assertFalse($asset->validate());
        self::assertArrayHasKey('loading', $asset->getErrors());
        self::assertSame('high', $asset->getFetchPriority());
    }

    public function testTranslatableAttributesAddThePerLanguageAttribute(): void
    {
        Yii::$app->getI18n()->setLanguages(['en-US', 'de']);

        $asset = TestAsset::create();
        self::assertNotContains('name_de', $asset->attributes());

        $asset->translatableAttributes = ['name'];
        $asset->resetCustomAttributes();

        self::assertContains('name_de', $asset->attributes());
    }

    public function testPopulateModelRelationsGroupsByClass(): void
    {
        $assets = [$this->createAsset(), $this->createAsset()];
        Asset::populateModelRelations($assets);

        foreach ($assets as $asset) {
            self::assertTrue($asset->isRelationPopulated('model'));
        }
    }

    protected function createModel(int $id): TestAssetModel
    {
        $model = TestAssetModel::create();
        $model->id = $id;

        return $model;
    }

    protected function createAsset(): TestAsset
    {
        $asset = TestAsset::create();
        $asset->populateFileRelation($this->getFileFromFixture('file-1'));
        $asset->populateModelRelation($this->createModel(1));

        return $asset;
    }
}
