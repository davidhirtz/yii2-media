<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Actions;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Yii;

/**
 * Deleted one at a time and the model recounted once at the end, so a selection spanning fifty assets of the same
 * record costs one recalculation rather than fifty. An asset that fails is reported and the rest are kept.
 *
 * The file counts stay per asset: a selection routinely spans as many files as it has rows, and a file the model
 * is not being deleted with has to be right the moment the row is gone.
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
            $this->model->recalculateAssetCount()->update();
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
