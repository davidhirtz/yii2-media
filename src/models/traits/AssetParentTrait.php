<?php

namespace davidhirtz\yii2\media\models\traits;

use davidhirtz\yii2\media\helpers\Sizes;
use davidhirtz\yii2\skeleton\models\traits\TypeAttributeTrait;

trait AssetParentTrait
{
    use TypeAttributeTrait;

    public function getAssetSizes(): ?string
    {
        return Sizes::format($this->getTypeOptions()['sizes'] ?? null);
    }

    public function getAssetTransformationNames(): array
    {
        return $this->getTypeOptions()['transformations'] ?? [];
    }
}
