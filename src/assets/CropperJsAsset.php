<?php

namespace davidhirtz\yii2\media\assets;

use davidhirtz\yii2\skeleton\assets\AdminAsset;
use yii\web\AssetBundle;

/**
 * Publishes the cropper.js asset files.
 * @link https://github.com/fengyuanchen/cropperjs
 */
class CropperJsAsset extends AssetBundle
{
    /**
     * @var array
     */
    public $css = ['cropper.min.css'];

    /**
     * @var array
     */
    public $depends = [
        AdminAsset::class,
    ];

    /**
     * @var array
     */
    public $js = ['cropper.min.js'];

    /**
     * @var string
     */
    public $sourcePath = '@npm/cropperjs/dist';
}
