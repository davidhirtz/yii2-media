<?php

namespace davidhirtz\yii2\media\helpers;

use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class Picture
{
    public static function tag(AssetInterface $asset, array $options = []): string
    {
        $imgOptions = ArrayHelper::remove($options, 'imgOptions', []);
        $webpOptions = ArrayHelper::remove($options, 'webpOptions', []);

        $sizes = ArrayHelper::remove($options, 'sizes', $asset->getSizes());
        $transformations = ArrayHelper::remove($options, 'transformations');

        $content = '';

        if ($asset->file->isTransformableImage()) {
            if ($transformations) {
                static::addSrcset($webpOptions, $asset->getSrcset($transformations, 'webp'), $sizes);
                $webpOptions['type'] ??= 'image/webp';

                $content .= Html::tag('source', '', $webpOptions);
            }
        }

        static::addSrcset($imgOptions, $asset->getSrcset($transformations), $sizes);
        
        $imgOptions['alt'] ??= $asset->getAltText();
        $imgOptions['loading'] ??= 'lazy';

        $content .= Html::tag('img', '', $imgOptions);

        return Html::tag('picture', $content, $options);
    }

    protected static function addSrcset(array &$options, array|string $srcset, ?array $sizes = null): void
    {
        if (is_array($srcset) && count($srcset) > 1) {
            $options['srcset'] = implode(',', static::srcset($srcset));
            $options['sizes'] = $sizes;
        } else {
            $options['srcset'] = static::src($srcset);
        }
    }

    protected static function srcset(array|string $srcset): array
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

    protected static function src(array|string $srcset): string
    {
        return is_string($srcset) ? $srcset : current($srcset);
    }
}