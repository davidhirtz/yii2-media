<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Interfaces;

use Hirtz\Media\Helpers\Size;
use Hirtz\Media\Models\Types\Traits\TransformationTypeTrait;
use Hirtz\Media\Transformations\Transformation;

/**
 * Declared by an asset's type and by its model's, which the asset falls back to.
 *
 * @see TransformationTypeTrait
 */
interface TransformationTypeInterface
{
    public function sizes(Size|string ...$sizes): static;

    public function transformations(Transformation|string ...$transformations): static;

    public function getSizes(): ?string;

    /**
     * @return list<string>
     */
    public function getTransformationNames(): array;
}
