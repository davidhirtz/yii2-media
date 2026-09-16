<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Interfaces;

use Hirtz\Media\Helpers\Size;
use Hirtz\Media\Models\Types\Traits\TransformationTypeTrait;
use Hirtz\Media\Transformations\Transformation;

/**
 * A type that declares what the rendered image asks for — the asset's own, and the type of the model it hangs on,
 * which is where an asset falls back to. Implemented via {@see TransformationTypeTrait}.
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
