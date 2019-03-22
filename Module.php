<?php

namespace davidhirtz\yii2\media;

use davidhirtz\yii2\skeleton\modules\ModuleTrait;

/**
 * Class Module
 * @package davidhirtz\yii2\media
 */
class Module extends \yii\base\Module
{
    use ModuleTrait;

    /**
     * @var string
     */
    public $uploadPath = 'uploads/';

    /**
     * @var array
     */
    public $allowedExtensions = ['gif', 'jpg', 'jpeg', 'png', 'webp', 'svg'];

    /**
     * @var bool
     */
    public $checkExtensionByMimeType = false;

    /**
     * @var bool
     */
    public $keepFilename = false;

    /**
     * @var bool
     */
    public $overwriteFiles = true;

    /**
     * @var array containing file relation information.
     */
    public $relations = [];
}