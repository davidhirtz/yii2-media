<?php

declare(strict_types=1);

namespace Hirtz\Media\Modules\Admin\Data;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Override;
use yii\data\ArrayDataProvider;

/**
 * @property Asset[] $models
 * @method Asset[] getModels()
 */
class AssetArrayDataProvider extends ArrayDataProvider
{
    public AssetModelInterface $model;

    #[Override]
    public function init(): void
    {
        $assets = $this->model->getAssets()
            ->withFiles()
            ->all();

        $this->model->populateAssetRelations($assets);
        $this->allModels = $assets;

        parent::init();
    }
}
