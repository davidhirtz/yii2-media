<?php

namespace davidhirtz\yii2\media\models\traits;

use davidhirtz\yii2\cms\models\traits\VisibleAttributeTrait;
use davidhirtz\yii2\media\helpers\Sizes;

trait AssetParentTrait
{
    use VisibleAttributeTrait;

    public function getAssetSizes(): ?string
    {
        return Sizes::format($this->getTypeOptions()['sizes'] ?? null);
    }

    public function getAssetTransformationNames(): array
    {
        return $this->getTypeOptions()['transformations'] ?? [];
    }

    public function getVisibleAssets(): array
    {
        return $this->isAttributeVisible('#assets') ? $this->assets : [];
    }
}