<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\assets;

use yii\web\AssetBundle;

class ImageCropAsset extends AssetBundle
{
    public $js = ['js/crop.js'];
    public $sourcePath = '@media/assets/dist';
}
