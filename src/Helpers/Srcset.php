<?php

declare(strict_types=1);

namespace Hirtz\Media\Helpers;

class Srcset
{
    /**
     * @param array<string, mixed> $options
     * @param array<int|string, string> $srcset
     * @param array<int|string, string>|string|null $sizes
     */
    public static function addHtmlAttributes(
        array &$options,
        array $srcset,
        array|string|null $sizes = null,
        ?string $fallback = null
    ): void {
        if (count($srcset) > 1) {
            $options['srcset'] = implode(',', static::getFormattedSrcset($srcset));
            $options['sizes'] ??= $sizes;
        } else {
            $options['src'] = current($srcset) ?: $fallback;
        }
    }

    /**
     * @param array<int|string, string>|string $srcset
     * @return list<string>
     */
    public static function getFormattedSrcset(array|string $srcset): array
    {
        $sortedSrcset = [];

        if (is_array($srcset) && count($srcset) > 1) {
            foreach ($srcset as $width => $url) {
                $sortedSrcset[$width] = "$url {$width}w";
            }

            ksort($sortedSrcset);
        }

        return array_values($sortedSrcset);
    }
}
