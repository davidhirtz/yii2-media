<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\widgets;

use davidhirtz\yii2\media\helpers\Html;
use davidhirtz\yii2\media\helpers\Srcset;
use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use davidhirtz\yii2\skeleton\helpers\ArrayHelper;
use davidhirtz\yii2\skeleton\widgets\Widget;

class Picture extends Widget
{
    public AssetInterface $asset;
    public array|string|null $sizes = null;
    public ?array $transformations = null;
    public array $imgOptions = [];
    public array $pictureOptions = [];
    public array $webpOptions = [];
    public string $defaultImageLoading = 'lazy';
    public bool $enableWebpTransformations = true;
    public bool $enableLegacyFileFormats = false;
    public bool $omitUnnecessaryPictureTag = true;

    public function init(): void
    {
        $this->sizes ??= $this->asset->getSizes();
        $this->transformations ??= $this->asset->getTransformationNames();

        if ($this->enableWebpTransformations) {
            $this->enableWebpTransformations = $this->transformations && $this->asset->file->isTransformableImage();
        } else {
            $this->enableLegacyFileFormats = true;
        }

        parent::init();
    }

    public function run(): string
    {
        return $this->getPictureTag();
    }

    public function getPictureTag(): string
    {
        $source = $this->enableWebpTransformations && $this->enableLegacyFileFormats ? $this->getWebpSourceTag() : '';
        $image = $this->getImageTag();

        if ($this->omitUnnecessaryPictureTag && !$source && !$this->pictureOptions) {
            return $image;
        }

        return Html::tag('picture', $source . $image, $this->pictureOptions);
    }

    public function getImageTag(): string
    {
        $srcset = $this->asset->getSrcset($this->transformations, $this->enableLegacyFileFormats ? null : 'webp');
        Srcset::addHtmlAttributes($this->imgOptions, $srcset, $this->sizes, $this->asset->file->getUrl());

        $this->imgOptions['alt'] ??= $this->asset->getAltText();
        $this->imgOptions['loading'] ??= $this->defaultImageLoading;

        return Html::tag('img', '', $this->imgOptions);
    }

    public function getWebpSourceTag(): string
    {
        $srcset = $this->asset->getSrcset($this->transformations, 'webp');

        if (!$srcset) {
            return '';
        }

        Srcset::addHtmlAttributes($this->webpOptions, $srcset, $this->sizes);

        $src = ArrayHelper::remove($this->webpOptions, 'src');
        $this->webpOptions['srcset'] ??= $src;

        $this->webpOptions['type'] ??= 'image/webp';

        return Html::tag('source', '', $this->webpOptions);
    }
}
