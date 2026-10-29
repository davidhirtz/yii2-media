<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Actions;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Yii;

/**
 * The model is recounted once at the end, but each file per asset, since a file has to be right the moment its row
 * is gone.
 */
class DeleteAssets
{
    /**
     * @var list<Asset>
     */
    private array $deleted = [];

    /**
     * @var list<Asset>
     */
    private array $failed = [];

    /**
     * @param Asset[] $assets
     */
    public function __construct(protected AssetModelInterface $model, protected array $assets)
    {
    }

    public function run(): bool
    {
        foreach ($this->assets as $asset) {
            $asset->setIsBatch(true);
            $asset->populateModelRelation($this->model);

            if ($asset->delete() === false) {
                $this->failed[] = $asset;
                continue;
            }

            $this->deleted[] = $asset;
        }

        if ($this->deleted) {
            $this->model->updateAssetCount();
        }

        return !$this->failed;
    }

    /**
     * @return list<Asset>
     */
    public function getDeleted(): array
    {
        return $this->deleted;
    }

    /**
     * @return list<Asset>
     */
    public function getFailed(): array
    {
        return $this->failed;
    }

    /**
     * @param Asset[] $assets
     */
    public static function create(AssetModelInterface $model, array $assets): static
    {
        $action = Yii::createObject(static::class, [$model, $assets]);
        $action->run();

        return $action;
    }
}
