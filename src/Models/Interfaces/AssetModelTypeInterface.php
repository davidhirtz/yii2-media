<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Interfaces;

use Hirtz\Media\Models\Types\Traits\AssetModelTypeTrait;

/**
 * @see AssetModelTypeTrait
 */
interface AssetModelTypeInterface extends TransformationTypeInterface
{
    public function allowAssets(bool $allowAssets = true): static;

    /**
     * A type cannot turn on what {@see AssetModelInterface::allowsAssets()} reports off.
     */
    public function allowsAssets(): bool;
}
