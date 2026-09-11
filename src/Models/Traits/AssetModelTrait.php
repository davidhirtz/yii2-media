<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Traits;

use Hirtz\Media\Helpers\Sizes;
use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Media\Models\Queries\AssetQuery;
use Hirtz\Skeleton\Models\Traits\TypeAttributeTrait;
use yii\helpers\Inflector;

/**
 * @mixin AssetModelInterface
 */
trait AssetModelTrait
{
    use TypeAttributeTrait;

    /**
     * The subclass scope lives in its `find()`, which a `joinWith()` would move into the outer `WHERE` and turn the
     * left join into an inner one.
     *
     * @return AssetQuery<Asset>
     */
    public function getAssets(): AssetQuery
    {
        $class = $this->getAssetClass();

        /** @var AssetQuery<Asset> */
        return $this->hasMany($class, ['model_id' => 'id'])
            ->andOnCondition([Asset::tableName() . '.[[model_class]]' => $class::getModelClass()]);
    }

    public function recalculateAssetCount(): static
    {
        $this->asset_count = (int)$this->getAssets()->count();
        return $this;
    }

    /**
     * @param Asset[]|null $assets
     */
    public function populateAssetRelations(?array $assets): void
    {
        $class = $this->getAssetClass();
        $related = [];

        foreach ($assets ?? [] as $asset) {
            if ($asset instanceof $class && $asset->model_id === $this->id) {
                $asset->populateModelRelation($this);
                $related[] = $asset;
            }
        }

        $this->populateRelation('assets', $related);
    }

    public function getAssetSizes(): ?string
    {
        return Sizes::format($this->getTypeOptions()['sizes'] ?? null);
    }

    /**
     * @return list<string>
     */
    public function getAssetTransformationNames(): array
    {
        return $this->getTypeOptions()['transformations'] ?? [];
    }

    public function getParamName(): string
    {
        return Inflector::slug($this->formName());
    }
}
