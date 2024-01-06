<?php

namespace davidhirtz\yii2\media\widgets;

use davidhirtz\yii2\media\helpers\Html;
use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use Yii;
use yii\base\BaseObject;

class Picture extends BaseObject
{
    public ?AssetInterface $asset = null;
    public array|string|null $sizes = null;
    public ?array $transformations = null;
    public array $imgOptions = [];
    public array $pictureOptions = [];
    public array $webpOptions = [];

    public string $defaultImageLoading = 'lazy';
    public bool $omitUnnecessaryPictureTag = true;

    public function init(): void
    {
        $this->sizes ??= $this->asset->getSizes();
        $this->transformations ??= $this->asset->getTransformationNames();

        parent::init();
    }

    public static function tag(AssetInterface $asset, array $options = []): string
    {
        $picture = Yii::$container->get(static::class, [], [
            'asset' => $asset,
            ...$options,
        ]);

        return $picture->render();
    }

    public function render(): string
    {
        return $this->getPictureTag();
    }

    protected function getPictureTag(): string
    {
        $source = $this->hasWebpTransformation() ? $this->getWebpSourceTag() : '';
        $image = $this->getImageTag();

        if ($this->omitUnnecessaryPictureTag && !$source && !$this->pictureOptions) {
            return $image;
        }

        return Html::tag('picture', $source . $image, $this->pictureOptions);
    }

    protected function getImageTag(): string
    {
        $srcset = $this->asset->getSrcset($this->transformations);

        if (count($srcset) > 1) {
            $this->imgOptions['srcset'] = implode(',', $this->getSrcset($srcset));
            $this->imgOptions['sizes'] ??= $this->sizes;
        } else {
            $this->imgOptions['src'] = current($srcset) ?: $this->asset->file->getUrl();
        }

        $this->imgOptions['alt'] ??= $this->asset->getAltText();
        $this->imgOptions['loading'] ??= $this->defaultImageLoading;

        return Html::tag('img', '', $this->imgOptions);
    }

    protected function getWebpSourceTag(): string
    {
        $srcset = $this->asset->getSrcset($this->transformations, 'webp');

        if (!$srcset) {
            return '';
        }

        if (count($srcset) > 1) {
            $this->webpOptions['srcset'] = implode(',', $this->getSrcset($srcset));
            $this->webpOptions['sizes'] ??= $this->sizes;
        } else {
            $this->webpOptions['src'] = current($srcset);
        }

        $this->webpOptions['type'] ??= 'image/webp';

        return Html::tag('source', '', $this->webpOptions);
    }

    protected function getSrcset(array|string $srcset): array
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

    protected function hasWebpTransformation(): bool
    {
        return $this->transformations && $this->asset->file->isTransformableImage();
    }
}
