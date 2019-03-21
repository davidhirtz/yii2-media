<?php

namespace davidhirtz\yii2\media\assets;

use yii\web\AssetBundle;

/**
 * Class AdminAsset.
 * @package davidhirtz\yii2\media\assets
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
    public $js = [
        YII_DEBUG ? 'js/admin.js' : 'js/admin.min.js',
    ];

    /**
     * @var array
     */
    public $depends = [
        'davidhirtz\yii2\skeleton\assets\AdminAsset',
    ];
}
