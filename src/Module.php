<?php

declare(strict_types=1);

namespace Hirtz\Media;

use Hirtz\Media\Images\Generators\FireflyImageGenerator;
use Hirtz\Media\Images\Generators\GoogleImageGenerator;
use Hirtz\Media\Images\Generators\ImageGeneratorInterface;
use Hirtz\Media\Images\Generators\OpenAiImageGenerator;
use Hirtz\Media\Images\Generators\StabilityImageGenerator;
use Hirtz\Media\Images\ImageProcessor;
use Hirtz\Media\Models\Asset;
use Hirtz\Media\Models\Collections\FolderCollection;
use Hirtz\Media\Models\File;
use Hirtz\Media\Models\Interfaces\AssetModelInterface;
use Hirtz\Media\Transformations\Transformation;
use Hirtz\Skeleton\Filters\PageCache;
use Hirtz\Skeleton\Models\CustomAttributes\CustomAttribute;
use Hirtz\Skeleton\Models\Interfaces\TypeAttributeInterface;
use Hirtz\Skeleton\Db\ActiveRecord;
use Closure;
use Override;
use Throwable;
use Yii;
use yii\base\InvalidConfigException;
use yii\caching\CacheInterface;
use Hirtz\Skeleton\Caching\TagDependency;

class Module extends \Hirtz\Skeleton\Base\Module
{
    /**
     * @var string[]
     */
    public array $allowedExtensions = ['gif', 'jpg', 'jpeg', 'png', 'svg'];

    public bool $autorotateImages = true;

    /**
     * Used by a transformation that names none, see {@see Transformation::avifQuality()}.
     */
    public int $avifQuality = 50;

    /**
     * Overridden for a CDN, and also set through `params.cdnUrl`.
     */
    public ?string $baseUrl = null;

    /**
     * A minimum width in pixels or a media query, by breakpoint name.
     *
     * @var array<string, int|string>
     */
    public array $breakpoints = [
        'xs' => 425,
        'sm' => 768,
        'md' => 1024,
        'lg' => 1200,
        'xl' => 1440,
    ];

    /**
     * Enable only if source files can be validated.
     */
    public bool $checkExtensionByMimeType = false;

    /**
     * @var array<string, int>
     */
    public array $defaultFolderOrder = ['position' => SORT_ASC];

    /**
     * Always off on a remote file system (`s3://`), which renames objects, not prefixes.
     */
    public bool $enableRenameFolders = true;

    /**
     * Disable for remote providers such as Amazon S3.
     */
    public bool $enableDeleteNonEmptyFolders = true;

    /**
     * The folder size up to which a path change records a redirect per file, `false` for never.
     *
     * @see \Hirtz\Media\Models\Actions\SaveFolderRedirects
     */
    public int|false $maxFolderRedirects = 1000;

    /**
     * @var list<class-string<Asset>>
     */
    public array $assetClasses = [];

    /**
     * Rebuilt by `file/references` beside the asset classes; the save keeps them current without it.
     *
     * @var list<class-string<ActiveRecord>>
     */
    public array $fileReferenceModelClasses = [];

    /**
     * Appended to every file's custom attributes, after its quality attributes.
     *
     * @var list<Closure(File): list<CustomAttribute>>
     */
    public array $fileCustomAttributes = [];

    /**
     * Above it a file custom attribute is an autocomplete rather than a select.
     *
     * @see \Hirtz\Media\Models\CustomAttributes\FileCustomAttribute::selectLimit()
     */
    public int $fileSelectLimit = 100;

    /**
     * `false` disables the cache.
     *
     * @see FolderCollection::getAll()
     */
    public int|null|false $folderCachedQueryDuration = 0;

    /**
     * A content-named directory (`3f9a2c1b/photo.jpg`) never serves a file under a URL a cache holds another one for.
     */
    public bool $hashDirectories = true;

    /**
     * A name already taken in the folder is numbered.
     *
     * @see \Hirtz\Media\Models\File::validateFilename()
     */
    public bool $keepFilename = true;

    /**
     * A name another record holds is still refused, since the unique key refuses it anyway.
     */
    public bool $overwriteFiles = false;

    /**
     * A configuration array passes another Intervention driver to its constructor.
     *
     * @var class-string<ImageProcessor>|array{class: class-string<ImageProcessor>}|ImageProcessor
     */
    public string|array|ImageProcessor $imageProcessor = ImageProcessor::class;

    /**
     * Where ImageMagick spills an image too large for its memory, `null` its own `/tmp`, often a small tmpfs on shared
     * hosting.
     */
    public ?string $imageTemporaryPath = '@runtime/imagick';

    /**
     * The services of MCP `generate_image`, each offered once a key is there ({@see static::$imageGeneratorKeys});
     * `null` removes one.
     *
     * @var array<string, class-string<ImageGeneratorInterface>|array{class: class-string<ImageGeneratorInterface>}|ImageGeneratorInterface|null>
     */
    public array $imageGenerators = [
        'openai' => OpenAiImageGenerator::class,
        'google' => GoogleImageGenerator::class,
        'stability' => StabilityImageGenerator::class,
        'firefly' => FireflyImageGenerator::class,
    ];

    /**
     * `site` (the generator's `apiKey`), `user` (each user's own) or `user-then-site`, which offers the API keys tab
     * without a site key.
     */
    public string $imageGeneratorKeys = 'user-then-site';

    /**
     * Per user and day, on the site's key.
     */
    public int $imageGeneratorDailyLimit = 20;

    /**
     * Used by a transformation that names none, see {@see Transformation::jpegQuality()}.
     */
    public int $jpegQuality = 75;

    /**
     * The longest side of an MCP `get_record` image preview, `null` for none.
     */
    public ?int $mcpPreviewSize = 1024;

    /**
     * Pixels per inch, used by a transformation that names none.
     */
    public int $resolution = 72;

    /**
     * The colour profile is kept, and ImageMagick 6 cannot encode a WebP from a source carrying a broken EXIF block.
     */
    public bool $stripMetadata = true;

    /**
     * Used by a transformation that names none, see {@see Transformation::webpQuality()}.
     */
    public int $webpQuality = 80;

    /**
     * @var list<string>
     */
    public array $transformableImageExtensions = ['jpg', 'jpeg', 'png'];

    /**
     * The longest side an upload is reduced to, `null` to keep every upload as it is.
     */
    public ?int $maxImageSize = 4096;

    /**
     * What {@see File::isVideo()} answers for, set by `yii2-media-video`.
     *
     * @var list<string>
     */
    public array $videoExtensions = ['mp4', 'ogg', 'webm'];

    /**
     * @var list<string>
     */
    public array $transformationExtensions = ['avif', 'webp'];

    /**
     * `null` for the first format {@see getTransformationExtensions()} answers.
     *
     * @var list<string>|null
     */
    public ?array $transformationQualityExtensions = null;

    /**
     * @var array<string, Transformation>
     */
    private array $transformations = [];

    private bool $hasTypeTransformations = false;

    /**
     * Defaults to "uploads" through {@see Bootstrap::bootstrap()}, so the URL rules read it without loading the module.
     */
    public ?string $uploadPath = null;

    /**
     * Defaults to `@webroot`.
     */
    public ?string $webroot = null;

    #[Override]
    public function init(): void
    {
        // not spread: a numeric name such as `335` is an `int` key, which unpacking renumbers
        $this->transformations = array_replace($this->createDefaultTransformations(), $this->transformations);

        $this->baseUrl ??= Yii::$app->params['cdnUrl'] ?? ('/' . ltrim((string)$this->uploadPath, '/'));
        $this->baseUrl = rtrim((string)$this->baseUrl, '/') . '/';

        $this->webroot ??= rtrim((string)Yii::getAlias('@webroot'), '/') . '/';
        $this->uploadPath = $this->webroot . rtrim((string)$this->uploadPath, '/') . '/';

        if (str_contains($this->uploadPath, '://')) {
            $this->enableRenameFolders = false;
        }

        parent::init();
    }

    /**
     * @param array<mixed> $transformations
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
     * An identical preset of the same name is kept and a different one refused, so a type cannot silently redefine it.
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
     * Sorted by width, so a srcset is built in ascending order.
     *
     * @return array<string, Transformation>
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

    /**
     * Read from the keys, a numeric name such as `335` would be an `int`.
     *
     * @return list<string>
     */
    public function getTransformationNames(): array
    {
        return array_map(strval(...), array_keys($this->getTransformations()));
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
     * The guard is set first, since `validate()` asks {@see hasTransformation()}, and the classes resolve through
     * `instance()`, where a project maps its own model.
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

    /**
     * The format an admin thumbnail is linked in, and the one a file's posted preview is written in.
     */
    public function getPreviewExtension(): string
    {
        $extensions = $this->getTransformationExtensions();
        return in_array('avif', $extensions, true) ? 'avif' : ($extensions[0] ?? 'jpg');
    }

    /**
     * Only the formats this server can encode, so a site without an AVIF encoder falls back to the next.
     *
     * @return list<string>
     */
    public function getTransformationExtensions(): array
    {
        return array_values(array_filter(
            $this->transformationExtensions,
            $this->getImageProcessor()->canEncode(...),
        ));
    }

    /**
     * @return list<string>
     */
    public function getTransformationQualityExtensions(): array
    {
        $extensions = $this->getTransformationExtensions();

        return $this->transformationQualityExtensions === null
            ? array_slice($extensions, 0, 1)
            : array_values(array_intersect($this->transformationQualityExtensions, $extensions));
    }

    /**
     * @return array<string, ImageGeneratorInterface>
     */
    public function getImageGenerators(): array
    {
        $generators = [];

        foreach ($this->imageGenerators as $name => $generator) {
            if ($generator !== null && !$generator instanceof ImageGeneratorInterface) {
                $generator = $this->imageGenerators[$name] = Yii::createObject($generator);
            }

            if ($generator instanceof ImageGeneratorInterface) {
                $generators[$name] = $generator;
            }
        }

        return $generators;
    }

    public function getImageProcessor(): ImageProcessor
    {
        if (!$this->imageProcessor instanceof ImageProcessor) {
            $this->imageProcessor = Yii::createObject($this->imageProcessor);

            if ($this->imageTemporaryPath !== null) {
                $this->imageProcessor->setTemporaryPath($this->imageTemporaryPath);
            }
        }

        return $this->imageProcessor;
    }

    /**
     * A host may list either function in `disable_functions`, which makes it undefined.
     */
    public function removeResourceLimits(): void
    {
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '-1');
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }
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
     * @return list<class-string<ActiveRecord>>
     */
    public function getFileReferenceModelClasses(): array
    {
        return array_values(array_unique([...$this->fileReferenceModelClasses, ...$this->getAssetClasses()]));
    }

    /**
     * @return list<class-string<Asset>>
     */
    public function getAssetClasses(): array
    {
        return $this->assetClasses;
    }
}
