<?php

namespace davidhirtz\yii2\media\helpers;

class Srcset
{
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

    public static function getFormattedSrcset(array|string $srcset): array
    {
        $sortedSrcset = [];

        if (is_array($srcset) && count($srcset) > 1) {
            foreach ($srcset as $width => $url) {
                $sortedSrcset[$width] = "$url {$width}w";
            }

            ksort($sortedSrcset);
        }

        return $sortedSrcset;
    }
}
