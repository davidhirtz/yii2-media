<?php

namespace davidhirtz\yii2\media\widgets;

use davidhirtz\yii2\media\helpers\Html;
use davidhirtz\yii2\media\helpers\Srcset;
use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use davidhirtz\yii2\skeleton\helpers\ArrayHelper;
use davidhirtz\yii2\skeleton\widgets\Widget;

class Picture extends Widget
{
    public ?AssetInterface $asset = null;

    /**
     * @var array|string|null the `sizes` attribute specifies of the image and source tags
     */
    public array|string|null $sizes = null;

    /**
     * @var array|null the transformations to apply to the image, only valid transformation names will
     * be applied
     */
    public ?array $transformations = null;

    /**
     * @var array the HTML attributes for the image tag
     */
    public array $imgOptions = [];

    /**
     * @var array the HTML attributes for the picture tag.
     * If this is empty and the `omitUnnecessaryPictureTag` option is set, the picture tag will be omitted
     */
    public array $pictureOptions = [];

    /**
     * @var array the HTML attributes for the webp source tag
     */
    public array $webpOptions = [];

    /**
     * @var string the default value for the `loading` attribute of the image tag
     */
    public string $defaultImageLoading = 'lazy';

    /**
     * @var bool whether to enable webp transformations
     */
    public bool $enableWebpTransformations = true;

    /**
     * @var bool whether to omit the picture tag if it is not necessary
     */
    public bool $omitUnnecessaryPictureTag = true;

    public function init(): void
    {
        $this->sizes ??= $this->asset->getSizes();
        $this->transformations ??= $this->asset->getTransformationNames();

        if ($this->enableWebpTransformations) {
            $this->enableWebpTransformations = $this->transformations && $this->asset->file->isTransformableImage();
        }

        parent::init();
    }

    public function run(): string
    {
        return $this->getPictureTag();
    }

    public function getPictureTag(): string
    {
        $source = $this->enableWebpTransformations ? $this->getWebpSourceTag() : '';
        $image = $this->getImageTag();

        if ($this->omitUnnecessaryPictureTag && !$source && !$this->pictureOptions) {
            return $image;
        }

        return Html::tag('picture', $source . $image, $this->pictureOptions);
    }

    public function getImageTag(): string
    {
        $srcset = $this->asset->getSrcset($this->transformations);
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

        // `<source src>` with a `<picture>` parent is invalid, change it to `srcset`
        $src = ArrayHelper::remove($this->webpOptions, 'src');
        $this->webpOptions['srcset'] ??= $src;

        $this->webpOptions['type'] ??= 'image/webp';

        return Html::tag('source', '', $this->webpOptions);
    }
}
