<?php

namespace davidhirtz\yii2\media\helpers;


use davidhirtz\yii2\media\models\AssetInterface;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * HTML Picture tag helper class.
 */
class Picture
{
    /**
     * @param AssetInterface $asset
     * @param array $options
     * @return string
     */
    public static function tag($asset, $options = []): string
    {
        $webpOptions = ArrayHelper::remove($options, 'webpOptions', []);
        $imgOptions = ArrayHelper::remove($options, 'imgOptions', []);

        $transformations = ArrayHelper::remove($options, 'transformations', $asset->getTransformationNames());
        $sizes = ArrayHelper::remove($options, 'sizes', $asset->getSrcsetSizes());

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

    /**
     * @param array $options
     * @param array $srcset
     * @param string $sizes
     */
    protected static function addSrcset(&$options, $srcset, $sizes = null)
    {
        if (is_array($srcset) && count($srcset) > 1) {
            $options['srcset'] = implode(',', static::srcset($srcset));
            $options['sizes'] = $sizes;
        } else {
            $options['srcset'] = static::src($srcset);
        }
    }

    /**
     * @param array $srcset
     * @return array
     */
    protected static function srcset($srcset): array
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

    /**
     * @param $srcset
     * @return string
     */
    protected static function src($srcset): string
    {
        return is_string($srcset) ? $srcset : current($srcset);
    }
}