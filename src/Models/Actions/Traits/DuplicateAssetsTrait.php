<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Actions\Traits;

use Hirtz\Media\Models\Actions\DuplicateAsset;
use Hirtz\Media\Models\Asset;
use Yii;

trait DuplicateAssetsTrait
{
    protected function duplicateAssets(): void
    {
        Yii::debug('Duplicating assets ...');

        $position = 0;

        foreach ($this->getAssets() as $asset) {
            DuplicateAsset::create([
                'asset' => $asset,
                'model' => $this->duplicate,
                'shouldUpdateModelAfterInsert' => false,
                'attributes' => [
                    'status' => $asset->status,
                    'position' => ++$position,
                ],
            ]);
        }
    }

    /**
     * @return Asset[]
     */
    protected function getAssets(): array
    {
        return $this->model->getAssets()->all();
    }
}
