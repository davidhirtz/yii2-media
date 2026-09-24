<?php

declare(strict_types=1);

namespace Hirtz\Media\Helpers;

use SimpleXMLElement;

/**
 * Reads the width and height of an image without decoding it.
 */
final class ImageSize
{
    /**
     * @return array{int, int}|null
     */
    public static function fromFile(string $filename, ?string $extension = null): ?array
    {
        $extension ??= pathinfo($filename, PATHINFO_EXTENSION);

        if (strtolower($extension) === 'svg') {
            return self::fromSvg($filename);
        }

        $size = @getimagesize($filename);

        return $size ? [$size[0], $size[1]] : null;
    }

    /**
     * A dimension with a unit other than pixels says nothing about the drawing's size, so the viewBox answers then.
     *
     * @return array{int, int}|null width and height from the attributes, else the viewBox
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
