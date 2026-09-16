<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Types\Traits;

use Hirtz\Media\Models\Interfaces\AssetModelTypeInterface;

/**
 * @mixin AssetModelTypeInterface
 */
trait AssetModelTypeTrait
{
    use TransformationTypeTrait;

    protected bool $allowsAssets = true;

    public function allowAssets(bool $allowAssets = true): static
    {
        $this->allowsAssets = $allowAssets;
        return $this;
    }

    public function allowsAssets(): bool
    {
        return $this->allowsAssets;
    }
}
