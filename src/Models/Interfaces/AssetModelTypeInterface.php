<?php

declare(strict_types=1);

namespace Hirtz\Media\Models\Interfaces;

use Hirtz\Media\Helpers\Size;
use Hirtz\Media\Transformations\Transformation;

/**
 * A type of a model that has assets: it declares what the rendered image asks for.
 */
interface AssetModelTypeInterface
{
    public function sizes(Size|string ...$sizes): static;

    public function transformations(Transformation|string ...$transformations): static;

    public function getSizes(): ?string;

    /**
     * @return list<string>
     */
    public function getTransformationNames(): array;
}
