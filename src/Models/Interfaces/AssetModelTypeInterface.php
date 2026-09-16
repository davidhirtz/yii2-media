<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Interfaces;

use Hirtz\Media\Models\Types\Traits\AssetModelTypeTrait;

/**
 * A type of a model that has assets: it declares what the rendered image asks for, and whether a record of this type
 * has assets at all. Implemented via {@see AssetModelTypeTrait}.
 */
interface AssetModelTypeInterface extends TransformationTypeInterface
{
    public function allowAssets(bool $allowAssets = true): static;

    /**
     * Whether a record of this type has assets. The installation decides first — a type cannot turn on what
     * {@see AssetModelInterface::allowsAssets()} reports off.
     */
    public function allowsAssets(): bool;
}
