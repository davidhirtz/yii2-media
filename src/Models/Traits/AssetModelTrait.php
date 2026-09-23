<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Traits;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Media\Models\Interfaces\AssetModelTypeInterface;
use Hirtz\Media\Models\Interfaces\TransformationTypeInterface;
use Hirtz\Media\Models\Queries\AssetQuery;
use Hirtz\Skeleton\Models\Traits\TypeAttributeTrait;

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

    public function updateAssetCount(): int
    {
        return $this->updateDenormalizedAttributes([
            'asset_count' => (int)$this->getAssets()->count(),
        ]);
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

    /**
     * The installation's own flag is the model's to add: a type can only narrow what the module turned on.
     */
    protected function typeAllowsAssets(): bool
    {
        $type = $this->getType();
        return !$type instanceof AssetModelTypeInterface || $type->allowsAssets();
    }

    public function getAssetSizes(): ?string
    {
        $type = $this->getType();
        return $type instanceof TransformationTypeInterface ? $type->getSizes() : null;
    }

    /**
     * @return list<string>
     */
    public function getAssetTransformationNames(): array
    {
        $type = $this->getType();
        return $type instanceof TransformationTypeInterface ? $type->getTransformationNames() : [];
    }
}
