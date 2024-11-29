<?php

declare(strict_types=1);

namespace davidhirtz\yii2\media\assets;

use yii\web\AssetBundle;

/**
 * Public asset bundle for the media admin module.
 */
class AdminAsset extends AssetBundle
{
    /**
     * @var string
     */
    public $sourcePath = '@media/assets/admin';

    /**
     * @var array
     */
    public $js = ['js/admin.min.js'];

    /**
     * @var array
     */
    public $depends = [
        \davidhirtz\yii2\skeleton\assets\AdminAsset::class,
    ];
}
