<?php

declare(strict_types=1);

namespace Hirtz\Media\assets;

use yii\web\AssetBundle;

class ImageCropAssetBundle extends AssetBundle
{
    public $js = ['js/crop.js'];
    public $sourcePath = '@media/../assets/dist';
}
