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
     * @var string the default upload path, defaults to "uploads" set via Bootstrap to access it for
     * dynamic url rule generation without loading the module.
     */
    public $uploadPath;

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
     * @var bool
     */
    public $tinyPngCompress = false;

    /**
     * @var array containing file transformation settings. Each transformation needs a unique name
     * set as key and transformation attributes as values eg. "width", "height", "imageOptions" or "scaleUp".
     */
    public $transformations = [];

    /**
     * @var array containing file relation information.
     */
    public $relations = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (!isset($this->transformations['admin'])) {
            $this->transformations['admin'] = [
                'width' => 120,
            ];
        }

        parent::init();
    }
}