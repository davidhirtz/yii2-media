<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\assets;

use davidhirtz\yii2\skeleton\assets\AbstractAssetBundle;

class ImageCropAsset extends AbstractAssetBundle
{
    public string $filename = 'crop.js';
    public $sourcePath = '@media/assets/dist';
}
