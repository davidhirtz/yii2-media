<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Models\Types;

use Hirtz\Media\Helpers\Size;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Media\Models\Traits\AssetModelTrait;
use Hirtz\Media\Models\Types\AssetType;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Media\Test\Models\TestAsset;
use Hirtz\Media\Test\TestCase;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Skeleton\Db\ActiveRecord;
use Hirtz\Skeleton\Models\Interfaces\TypeAttributeInterface;
use Hirtz\Skeleton\Models\Traits\AdminModelTrait;
use Override;
use yii\base\InvalidConfigException;

class AssetModelTypeTest extends TestCase
{
    use ModuleTrait;

    #[Override]
    protected function tearDown(): void
    {
        TypedAssetModel::$types = null;
        parent::tearDown();
    }

    public function testTheSizesAreJoinedIntoTheAttribute(): void
    {
        TypedAssetModel::$types = [
            AssetType::make(1)
                ->name('Default')
                ->sizes(Size::breakpoint('sm', '100vw'), Size::mediaQuery('(max-width: 1023px)', '75vw'), '960px'),
        ];

        $model = TypedAssetModel::create();
        $model->type = 1;

        self::assertSame('(max-width:767px) 100vw,(max-width: 1023px) 75vw,960px', $model->getAssetSizes());
    }

    public function testAModelWithoutSizesHasNone(): void
    {
        TypedAssetModel::$types = [AssetType::make(1)->name('Default')];

        $model = TypedAssetModel::create();
        $model->type = 1;

        self::assertNull($model->getAssetSizes());
        self::assertSame([], $model->getAssetTransformationNames());
    }

    public function testABareSizeBeforeAConditionalOneThrows(): void
    {
        TypedAssetModel::$types = [
            AssetType::make(1)
                ->name('Default')
                ->sizes('100vw', Size::breakpoint('sm', '50vw')),
        ];

        $this->expectException(InvalidConfigException::class);
        TypedAssetModel::getTypeDefinitions();
    }

    public function testASelfDescribingNameIsRegisteredOnTheModule(): void
    {
        TypedAssetModel::$types = [
            AssetType::make(1)
                ->name('Default')
                ->transformations('w_1200'),
        ];

        self::assertFalse(self::getModule()->hasTransformation('w_1200'));

        TypedAssetModel::getTypeDefinitions();

        self::assertSame(1200, self::getModule()->getTransformation('w_1200')?->getWidth());
    }

    public function testAnInlineTransformationIsRegisteredOnTheModule(): void
    {
        TypedAssetModel::$types = [
            AssetType::make(1)
                ->name('Default')
                ->transformations(Transformation::make('hero')->width(1600)->height(400)),
        ];

        TypedAssetModel::getTypeDefinitions();

        self::assertSame(1600, self::getModule()->getTransformation('hero')?->getWidth());
        $model = TypedAssetModel::create();
        $model->type = 1;

        self::assertSame(['hero'], $model->getAssetTransformationNames());
    }

    public function testAnUnconfiguredNameThatDescribesNothingThrows(): void
    {
        TypedAssetModel::$types = [
            AssetType::make(1)
                ->name('Default')
                ->transformations('does-not-exist'),
        ];

        $this->expectException(InvalidConfigException::class);
        TypedAssetModel::getTypeDefinitions();
    }

    public function testAnInlineTransformationConflictingWithAConfiguredOneThrows(): void
    {
        self::getModule()->addTransformation(Transformation::make('hero')->width(800));

        TypedAssetModel::$types = [
            AssetType::make(1)
                ->name('Default')
                ->transformations(Transformation::make('hero')->width(1600)),
        ];

        $this->expectException(InvalidConfigException::class);
        TypedAssetModel::getTypeDefinitions();
    }

    public function testAnIdenticalInlineTransformationIsANoOp(): void
    {
        self::getModule()->addTransformation(Transformation::make('hero')->width(800));

        TypedAssetModel::$types = [
            AssetType::make(1)
                ->name('Default')
                ->transformations(Transformation::make('hero')->width(800)),
        ];

        TypedAssetModel::getTypeDefinitions();

        self::assertSame(800, self::getModule()->getTransformation('hero')?->getWidth());
    }
}

/**
 * Has no table, as {@see \Hirtz\Media\Test\Models\TestAssetModel}: only the type declaration is exercised.
 *
 * @property int $type
 */
class TypedAssetModel extends ActiveRecord implements AssetModelInterface, TypeAttributeInterface
{
    use AdminModelTrait;
    use AssetModelTrait;

    /**
     * @var list<AssetType>|null
     */
    public static ?array $types = null;

    #[Override]
    public function attributes(): array
    {
        return ['id', 'type', 'asset_count'];
    }

    #[Override]
    public static function getTypeClass(): string
    {
        return AssetType::class;
    }

    #[Override]
    public static function getTypes(): array
    {
        return static::$types ?? [AssetType::make(1)->name('Default')];
    }

    public function getAssetClass(): string
    {
        return TestAsset::class;
    }

    public function hasAssetsEnabled(): bool
    {
        return true;
    }

    #[Override]
    public function recalculateAssetCount(): static
    {
        return $this;
    }

    public function getAdminRoute(): array|false
    {
        return false;
    }
}
