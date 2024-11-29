<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\helpers;

use davidhirtz\yii2\media\models\File;
use yii\base\InvalidConfigException;

class AspectRatio implements \Stringable
{
    public readonly float $width;
    public readonly float $height;

    public function __construct(File|int $width, ?int $height = null)
    {
        if ($width instanceof File) {
            $height = $width->height;
            $width = $width->width;
        }

        if (!$height) {
            throw new InvalidConfigException('Height must be provided');
        }

        $gcd = $this->getGreatestCommonDivisor($width, $height);

        $this->width = $width / $gcd;
        $this->height = $height / $gcd;
    }

    public function __toString(): string
    {
        return "$this->width/$this->height";
    }

    private function getGreatestCommonDivisor(int $width, int $height): float
    {
        return $height !== 0
            ? $this->getGreatestCommonDivisor($height, $width % $height)
            : $width;
    }
}
