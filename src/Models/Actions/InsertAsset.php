<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Actions;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Yii;

class InsertAsset
{
    private Asset $asset;
    private bool $isInserted = false;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        protected AssetModelInterface $model,
        protected File $file,
        protected array $attributes = [],
    ) {
    }

    public function run(): bool
    {
        $asset = Yii::createObject($this->model->getAssetClass());
        $asset->loadDefaultValues();
        $asset->populateModelRelation($this->model);
        $asset->populateFileRelation($this->file);

        if ($this->attributes) {
            $asset->load($this->attributes, '');
        }

        $this->asset = $asset;
        $this->isInserted = $asset->insert();

        return $this->isInserted;
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function isInserted(): bool
    {
        return $this->isInserted;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function create(AssetModelInterface $model, File $file, array $attributes = []): static
    {
        $action = Yii::createObject(static::class, [$model, $file, $attributes]);
        $action->run();

        return $action;
    }
}
