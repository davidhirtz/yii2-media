<?php

declare(strict_types=1);

namespace Hirtz\Media\Images\Generators;

use RuntimeException;

/**
 * A refusal the service gave, worded for the user: a rejected prompt, a rejected key, the service unreachable.
 */
class ImageGeneratorException extends RuntimeException
{
}
