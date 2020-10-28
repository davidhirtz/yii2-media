<?php

namespace davidhirtz\yii2\media;

use davidhirtz\yii2\media\composer\Bootstrap;
use davidhirtz\yii2\skeleton\modules\ModuleTrait;
use Yii;

/**
 * Class Module
 * @package davidhirtz\yii2\media
 */
class Module extends \yii\base\Module
{
    use ModuleTrait;

    /**
     * @var string the webroot or remote file system. Default to "@webroot".
     */
    public $webroot;

    /**
     * @var string the default upload path, defaults to "uploads" set via {@link Bootstrap::bootstrap()} to access
     * it for dynamic url rule generation without loading the module.
     */
    public $uploadPath;

    /**
     * @var string the default base url, override this to set a CDN url. Can also be set via
     * {@link Yii::$app->params['cdnUrl']}.
     */
    public $baseUrl;

    /**
     * @var array
     */
    public $allowedExtensions = ['gif', 'jpg', 'jpeg', 'png', 'svg'];

    /**
     * @var bool
     */
    public $checkExtensionByMimeType = false;

    /**
     * @var bool
     */
    public $keepFilename = false;

    /**
     * @var int|false if set to value this splits files into sub folders on upload, disabled by default
     */
    public $maxFilesPerFolder = false;

    /**
     * @var bool
     */
    public $overwriteFiles = true;

    /**
     * @var bool whether folders can be renamed. This can be disabled for remote providers such as
     * Amazon S3 hosting.
     */
    public $enableRenameFolders = true;

    /**
     * @var bool whether folders can be deleted when they still contain files. This can be disabled
     * for remote providers such as Amazon S3 hosting.
     */
    public $enableDeleteNonEmptyFolders = true;

    /**
     * @var string
     */
    public $defaultFolderOrder = ['position' => SORT_ASC];

    /**
     * @var array containing file transformation settings. Each transformation needs a unique name
     * set as key and transformation attributes as values eg. "width", "height", "imageOptions" or "scaleUp".
     */
    public $transformations = [];

    /**
     * @var array containing additional file transformation extensions.
     */
    public $transformationExtensions = ['webp'];

    /**
     * @var array containing file relation information.
     */
    public $assets = [];

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

        if ($this->webroot === null) {
            $this->webroot = rtrim(Yii::getAlias('@webroot'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        if ($this->baseUrl === null) {
            $this->baseUrl = Yii::$app->params['cdnUrl'] ?? ('/' . str_replace(DIRECTORY_SEPARATOR, '/', $this->uploadPath));
        }

        $this->baseUrl = rtrim($this->baseUrl, '/') . '/';
        $this->uploadPath = $this->webroot . rtrim($this->uploadPath, $this->getDirectorySeparator()) . $this->getDirectorySeparator();

        parent::init();
    }

    /**
     * @return string
     */
    public function getDirectorySeparator(): string
    {
        return $this->webrootIsLocal() ? DIRECTORY_SEPARATOR : '/';
    }

    /**
     * @return bool
     */
    public function webrootIsLocal(): bool
    {
        return stream_is_local($this->webroot);
    }
}