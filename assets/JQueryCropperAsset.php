<?php

namespace davidhirtz\yii2\media\assets;

use yii\web\AssetBundle;

/**
 * Class JQueryCropperAsset
 * @package davidhirtz\yii2\media\assets
 */
class JQueryCropperAsset extends AssetBundle
{
    /**
     * @var array
     */
    public $css = [YII_DEBUG ? 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.6/cropper.css' : 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.6/cropper.min.css'];

    /**
     * @var array
     */
    public $js = [YII_DEBUG ? 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.6/cropper.js' : 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.6/cropper.min.js'];

    /**
     * @var array
     */
    public $depends = [
        'davidhirtz\yii2\media\assets\AdminAsset',
    ];
}
