<?php

declare(strict_types=1);

namespace Hirtz\Media\Helpers;

use SimpleXMLElement;

final class ImageSize
{
    /**
     * An EXIF orientation turning it by 90° swaps width and height, as a browser and every transformation turn it
     * upright.
     *
     * @return array{int, int}|null
     */
    public static function fromFile(string $filename, ?string $extension = null): ?array
    {
        $extension ??= pathinfo($filename, PATHINFO_EXTENSION);

        if (strtolower($extension) === 'svg') {
            return self::fromSvg($filename);
        }

        $size = @getimagesize($filename);

        if (!$size || !self::isTypeOfExtension($size[2], $extension)) {
            return null;
        }

        return self::getOrientation($filename) >= 5 ? [$size[1], $size[0]] : [$size[0], $size[1]];
    }

    /**
     * A file named for one raster format must hold it: PHP 8.5 answers a size for SVG markup named `photo.jpg` too.
     */
    private static function isTypeOfExtension(int $type, string $extension): bool
    {
        $types = match (strtolower($extension)) {
            'jpg', 'jpeg' => [IMAGETYPE_JPEG],
            'png' => [IMAGETYPE_PNG],
            'gif' => [IMAGETYPE_GIF],
            'webp' => [IMAGETYPE_WEBP],
            'avif' => [IMAGETYPE_AVIF],
            'bmp' => [IMAGETYPE_BMP],
            'tif', 'tiff' => [IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM],
            default => null,
        };

        return $types === null || in_array($type, $types, true);
    }

    /**
     * `1` (top left) when it has none.
     */
    public static function getOrientation(string $filename): int
    {
        $type = @exif_imagetype($filename);

        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM], true)) {
            return 1;
        }

        $exif = @exif_read_data($filename, 'IFD0');
        $orientation = is_array($exif) ? (int)($exif['Orientation'] ?? 1) : 1;

        return $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
    }

    /**
     * A dimension with a unit other than pixels says nothing about the drawing's size, so the viewBox answers then.
     *
     * @return array{int, int}|null
     */
    public static function fromSvg(string $filename): ?array
    {
        $svg = self::loadSvg($filename);

        if (!$svg) {
            return null;
        }

        $attributes = $svg->attributes();

        $width = self::getPixels((string)$attributes?->width);
        $height = self::getPixels((string)$attributes?->height);

        if ($width !== null && $height !== null) {
            return [$width, $height];
        }

        $viewBox = preg_split('/[\s,]+/', trim((string)$attributes?->viewBox)) ?: [];

        if (count($viewBox) === 4 && is_numeric($viewBox[2]) && is_numeric($viewBox[3])) {
            return [(int)$viewBox[2], (int)$viewBox[3]];
        }

        return null;
    }

    private static function loadSvg(string $filename): ?SimpleXMLElement
    {
        $contents = @file_get_contents($filename);

        if (!$contents) {
            return null;
        }

        $useErrors = libxml_use_internal_errors(true);

        try {
            $svg = simplexml_load_string($contents, options: LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($useErrors);
        }

        return $svg && $svg->getName() === 'svg' ? $svg : null;
    }

    private static function getPixels(string $value): ?int
    {
        return preg_match('/^\s*(\d+(?:\.\d+)?)\s*(?:px)?\s*$/', $value, $matches)
            ? (int)$matches[1]
            : null;
    }
}
