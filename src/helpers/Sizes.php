<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\helpers;

use davidhirtz\yii2\media\modules\ModuleTrait;

class Sizes
{
    use ModuleTrait;

    public static function format(array|string|null $sizes = null, ?array $breakpoints = null): string
    {
        if (!is_array($sizes)) {
            return $sizes ?? '100vw';
        }

        $breakpoints ??= static::getModule()->breakpoints;
        $widths = [];

        foreach ($sizes as $condition => $width) {
            if ($breakpoints[$condition] ?? false) {
                $condition = $breakpoints[$condition];

                if (is_int($condition)) {
                    $maxWidth = $condition - 1;
                    $condition = "(max-width:{$maxWidth}px)";
                }
            }

            if (is_string($condition)) {
                $width = "$condition $width";
            }

            $widths[] = $width;
        }

        return implode(',', $widths);
    }
}
