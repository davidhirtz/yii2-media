<?php

declare(strict_types=1);

namespace Hirtz\Media\Images\Generators;

final readonly class GeneratedImage
{
    public function __construct(
        public string $content,
        public string $extension,
    ) {
    }
}
