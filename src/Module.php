<?php

declare(strict_types=1);

namespace Hirtz\Media;

use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Skeleton\Filters\PageCache;
use Hirtz\Skeleton\Models\Interfaces\TypeAttributeInterface;
use Override;
use Throwable;
use Yii;
use yii\base\InvalidConfigException;
use yii\caching\CacheInterface;
use yii\caching\TagDependency;

class Module extends \Hirtz\Skeleton\Base\Module
{
    /**
     * @var string[] containing the allowed file extensions
     */
    public array $allowedExtensions = ['gif', 'jpg', 'jpeg', 'png', 'svg'];

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
     * @var array<string, int|string> media query breakpoints. The key is the breakpoint name and the value is
     * either the minimum width in pixels or the media query string.
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
     * @var array<string, int> the default folder order.
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
     * @var int|false the number of files a folder may hold for a path change to record a redirect per file, or
     * `false` to never record them. Above it the rename still happens and the redirects do not.
     * @see \Hirtz\Media\Models\Actions\SaveFolderRedirects
     */
    public int|false $maxFolderRedirects = 1000;

    /**
     * @var list<class-string<Asset>> the registered asset subclasses, one per model that has assets.
     */
    public array $assets = [];

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
     * @var bool whether an upload keeps its own filename rather than being renamed to a random string, defaults
     * to `true`. A name already taken in the folder is numbered.
     * @see \Hirtz\Media\Models\File::validateFilename()
     */
    public bool $keepFilename = true;

    /**
     * @var bool whether files should be overwritten if a file with the same name already exists, setting this to `true`
     * can have a lot of complications with assets linking to the same file in the file system.
     */
    public bool $overwriteFiles = false;

    /**
     * @var list<string> containing file extensions which can be transformed and modified to
     * `transformationExtensions` file types.
     */
    public array $transformableImageExtensions = ['jpg', 'jpeg', 'png'];

    /**
     * @var list<string> additional file transformation extensions.
     */
    public array $transformationExtensions = ['avif', 'webp'];

    /**
     * @var array<string, Transformation>
     */
    private array $transformations = [];

    private bool $hasTypeTransformations = false;

    /**
     * @var string|null the default upload-path, defaults to "uploads" set via {@see Bootstrap::bootstrap()} to access
     * it for dynamic url rule generation without loading the module.
     */
    public ?string $uploadPath = null;

    /**
     * @var string|null the webroot or remote file system. Default to "@webroot".
     */
    public ?string $webroot = null;

    #[Override]
    public function init(): void
    {
        $this->transformations = [
            ...$this->createDefaultTransformations(),
            ...$this->transformations,
        ];

        $this->baseUrl ??= Yii::$app->params['cdnUrl'] ?? ('/' . ltrim((string)$this->uploadPath, '/'));
        $this->baseUrl = rtrim((string)$this->baseUrl, '/') . '/';

        $this->webroot ??= rtrim((string)Yii::getAlias('@webroot'), '/') . '/';
        $this->uploadPath = $this->webroot . rtrim((string)$this->uploadPath, '/') . '/';

        parent::init();
    }

    /**
     * @param array<mixed> $transformations the module configuration, which is unvalidated by definition
     */
    public function setTransformations(array $transformations): void
    {
        $this->transformations = [];

        foreach ($transformations as $transformation) {
            if (!$transformation instanceof Transformation) {
                $given = get_debug_type($transformation);
                throw new InvalidConfigException(static::class . '::$transformations must be a list of ' . Transformation::class . ", got $given.");
            }

            $this->transformations[$transformation->name] = $transformation;
        }
    }

    /**
     * A transformation of the same name is kept when it is identical and refused when it is not, so a type
     * declaring a preset the configuration already names cannot silently redefine it.
     */
    public function addTransformation(Transformation $transformation): void
    {
        $current = $this->transformations[$transformation->name] ?? null;

        if ($current && $current != $transformation) {
            throw new InvalidConfigException("Transformation \"{$transformation->name}\" is already configured with different dimensions.");
        }

        $this->transformations[$transformation->name] = $transformation;
    }

    public function removeTransformation(string $name): void
    {
        unset($this->transformations[$name]);
    }

    /**
     * @return array<string, Transformation> sorted by width, so a srcset is built in ascending order
     */
    public function getTransformations(): array
    {
        $this->ensureTypeTransformations();

        uasort(
            $this->transformations,
            static fn (Transformation $a, Transformation $b): int => $a->getWidth() <=> $b->getWidth(),
        );

        return $this->transformations;
    }

    public function getTransformation(string $name): ?Transformation
    {
        $this->ensureTypeTransformations();
        return $this->transformations[$name] ?? null;
    }

    public function hasTransformation(string $name): bool
    {
        return $this->getTransformation($name) !== null;
    }

    /**
     * A type's `transformations()` registers what it declares, so nothing has to be called from a configuration.
     * The guard is set before the models resolve, since a definition's `validate()` asks {@see hasTransformation()}
     * on its way through {@see addTransformation()}. The classes are resolved through `instance()`: a project maps
     * its own model over the bundle's in the container, and the types live on the project's.
     */
    protected function ensureTypeTransformations(): void
    {
        if ($this->hasTypeTransformations) {
            return;
        }

        $this->hasTypeTransformations = true;

        try {
            foreach ($this->getAssetClasses() as $class) {
                $class::instance()::getTypeDefinitions();
                $modelClass = $class::getModelClass();

                if (is_a($modelClass, TypeAttributeInterface::class, true)) {
                    $modelClass::instance()::getTypeDefinitions();
                }
            }
        } catch (Throwable $exception) {
            $this->hasTypeTransformations = false;
            throw $exception;
        }
    }

    /**
     * @return array<string, Transformation>
     */
    protected function createDefaultTransformations(): array
    {
        return [
            Transformation::NAME_ADMIN => Transformation::make(Transformation::NAME_ADMIN)
                ->width(120),
            Transformation::NAME_OPEN_GRAPH => Transformation::make(Transformation::NAME_OPEN_GRAPH)
                ->width(1200)
                ->height(630)
                ->keepAspectRatio(),
        ];
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

    /**
     * @param class-string<AssetModelInterface>|null $modelClass
     * @return class-string<Asset>|null
     */
    public function getAssetClass(?string $modelClass): ?string
    {
        if ($modelClass === null) {
            return null;
        }

        foreach ($this->getAssetClasses() as $class) {
            if ($class::getModelClass() === $modelClass) {
                return $class;
            }
        }

        return null;
    }

    /**
     * @return list<class-string<Asset>>
     */
    public function getAssetClasses(): array
    {
        return $this->assets;
    }
}
