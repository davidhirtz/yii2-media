<?php

declare(strict_types=1);

namespace Hirtz\Media\Test\Images;

use Hirtz\Media\Images\ImageProcessor;
use Intervention\Image\Interfaces\ImageInterface;
use Override;

/**
 * Encodes nothing for the extensions in `$unencodable`, as Imagick does for AVIF on a server whose libheif lacks the
 * encoder plugin (#271), and ignores the quality of those in `$qualityIgnored`, as ImageMagick 6 does for WebP.
 */
class TestImageProcessor extends ImageProcessor
{
    /**
     * @var list<string>
     */
    public array $unencodable = [];

    /**
     * @var list<string>
     */
    public array $qualityIgnored = [];

    #[Override]
    public function appliesQuality(string $extension): bool
    {
        return !in_array($extension, $this->qualityIgnored, true) && parent::appliesQuality($extension);
    }

    #[Override]
    protected function encode(ImageInterface $image, string $extension, ?int $quality = null): string
    {
        return in_array($extension, $this->unencodable, true) ? '' : parent::encode($image, $extension, $quality);
    }
}
