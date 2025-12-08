<?php

declare(strict_types=1);

namespace Hirtz\Media\models\traits;

use Hirtz\Media\helpers\Sizes;
use Hirtz\Skeleton\Models\Traits\TypeAttributeTrait;
use yii\helpers\Inflector;

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

    public function getParamName(): string
    {
        return Inflector::slug($this->formName());
    }
}
