<?php

namespace davidhirtz\yii2\media;

use davidhirtz\yii2\media\composer\Bootstrap;
use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use davidhirtz\yii2\skeleton\filters\PageCache;
use davidhirtz\yii2\skeleton\modules\ModuleTrait;
use Yii;
use yii\caching\CacheInterface;
use yii\caching\TagDependency;

class Module extends \yii\base\Module
{
    use ModuleTrait;

    /**
     * @var string|null the webroot or remote file system. Default to "@webroot".
     */
    public ?string $webroot = null;

    /**
     * @var string|null the default upload-path, defaults to "uploads" set via {@link Bootstrap::bootstrap()} to access
     * it for dynamic url rule generation without loading the module.
     */
    public ?string $uploadPath = null;

    /**
     * @var string|null the default base url, override this to set a CDN url. Can also be set via
     * {@link Yii::$app->params['cdnUrl']}.
     */
    public ?string $baseUrl = null;

    /**
     * @var string[] containing the allowed file extensions
     */
    public array $allowedExtensions = ['gif', 'jpg', 'jpeg', 'png', 'svg'];

    /**
     * @var string[] containing file extensions which can be transformed and modified to `transformationExtensions`
     * file types.
     */
    public array $transformableImageExtensions = ['jpg', 'jpeg', 'png'];

    /**
     * @var array containing additional file transformation extensions.
     */
    public array $transformationExtensions = ['webp'];

    /**
     * @var bool whether uploads should be automatically rotated based on their EXIF data.
     */
    public bool $autorotateImages = false;

    /**
     * @var bool whether uploads should be checked via mimetype rather than extension. Enable only if source files can
     * be validated.
     */
    public bool $checkExtensionByMimeType = false;

    /**
     * @var bool whether filename should not be replaced by unique names, defaults to `false`
     */
    public bool $keepFilename = false;

    /**
     * @var int|false if set to value this splits files into subfolders on upload, disabled by default
     */
    public bool $maxFilesPerFolder = false;

    /**
     * @var bool whether files should be overwritten if a file with the same name already exists, setting this to `true`
     * can have a lot of complications with assets linking to the same file in the file system.
     */
    public bool $overwriteFiles = false;

    /**
     * @var bool whether folders can be renamed. This can be disabled for remote providers such as
     * Amazon S3 hosting.
     */
    public bool $enableRenameFolders = true;

    /**
     * @var bool whether folders can be deleted when they still contain files. This can be disabled
     * for remote providers such as Amazon S3 hosting.
     */
    public bool $enableDeleteNonEmptyFolders = true;

    /**
     * @var array containing the default folder order.
     */
    public array $defaultFolderOrder = ['position' => SORT_ASC];

    /**
     * @var array containing file transformation settings. Each transformation needs a unique name
     * set as key and transformation attributes as values e.g. `width`, `height`, `imageOptions` or `scaleUp`.
     */
    public array $transformations = [];

    /**
     * @var \davidhirtz\yii2\media\models\interfaces\AssetInterface[] containing asset classes that are related to files.
     */
    public array $assets = [];

    public function init(): void
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

    public function invalidatePageCache(): void
    {
        if ($cache = $this->getCache()) {
            TagDependency::invalidate($cache, PageCache::TAG_DEPENDENCY_KEY);
        }
    }

    public function getCache(): ?CacheInterface
    {
        return Yii::$app->getCache();
    }

    public function getDirectorySeparator(): string
    {
        return $this->webrootIsLocal() ? DIRECTORY_SEPARATOR : '/';
    }

    public function webrootIsLocal(): bool
    {
        return stream_is_local($this->webroot);
    }
}