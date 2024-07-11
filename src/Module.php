<?php

namespace davidhirtz\yii2\media;

use davidhirtz\yii2\media\models\collections\FolderCollection;
use davidhirtz\yii2\media\models\interfaces\AssetInterface;
use davidhirtz\yii2\skeleton\filters\PageCache;
use davidhirtz\yii2\skeleton\modules\ModuleTrait;
use Yii;
use yii\caching\CacheInterface;
use yii\caching\TagDependency;

class Module extends \davidhirtz\yii2\skeleton\base\Module
{
    use ModuleTrait;

    /**
     * @var string[] containing the allowed file extensions
     */
    public array $allowedExtensions = ['gif', 'jpg', 'jpeg', 'png', 'svg'];

    /**
     * @var AssetInterface[] containing asset classes that are related to files.
     */
    public array $assets = [];

    /**
     * @var bool whether uploads should be automatically rotated based on their EXIF data.
     */
    public bool $autorotateImages = false;

    /**
     * @var string|null the default base url, override this to set a CDN url. Can also be set via
     * {@see Yii::$app->params['cdnUrl']}.
     */
    public ?string $baseUrl = null;

    /**
     * @var array containing media query breakpoints. The key is the breakpoint name and the value is either the minimum
     * width in pixels or the media query string.
     */
    public array $breakpoints = [
        'xs' => 425,
        'sm' => 768,
        'md' => 1024,
        'lg' => 1200,
        'xl' => 1440,
    ];

    /**
     * @var bool whether uploads should be checked via mimetype rather than extension. Enable only if source files can
     * be validated.
     */
    public bool $checkExtensionByMimeType = false;

    /**
     * @var array containing the default folder order.
     */
    public array $defaultFolderOrder = ['position' => SORT_ASC];

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
     * @var int|null|false duration in seconds for caching the folder query. Set to `false` to disable cache.
     * @see FolderCollection::getAll()
     */
    public int|null|false $folderCachedQueryDuration = 0;

    /**
     * @var int|false if set to value this splits files into subfolders on upload, disabled by default
     */
    public int|false $maxFilesPerFolder = false;

    /**
     * @var bool whether filename should not be replaced by unique names, defaults to `false`
     */
    public bool $keepFilename = false;

    /**
     * @var bool whether files should be overwritten if a file with the same name already exists, setting this to `true`
     * can have a lot of complications with assets linking to the same file in the file system.
     */
    public bool $overwriteFiles = false;

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
     * @var array<string, array> containing file transformation settings. Each transformation needs a unique name
     * set as key and transformation attributes as values e.g. `width`, `height`, `imageOptions` or `scaleUp`.
     */
    public array $transformations = [];

    /**
     * @var string|null the default upload-path, defaults to "uploads" set via {@see Bootstrap::bootstrap()} to access
     * it for dynamic url rule generation without loading the module.
     */
    public ?string $uploadPath = null;

    /**
     * @var string|null the webroot or remote file system. Default to "@webroot".
     */
    public ?string $webroot = null;

    public function init(): void
    {
        if (!isset($this->transformations['admin'])) {
            $this->transformations['admin'] = [
                'width' => 120,
            ];
        }

        $this->baseUrl ??= Yii::$app->params['cdnUrl'] ?? ('/' . ltrim((string)$this->uploadPath, '/'));
        $this->baseUrl = rtrim((string)$this->baseUrl, '/') . '/';

        $this->webroot ??= rtrim((string)Yii::getAlias('@webroot'), '/') . '/';
        $this->uploadPath = $this->webroot . rtrim((string)$this->uploadPath, '/') . '/';

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
}
