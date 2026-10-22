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
use yii\caching\TagDependency;

class Module extends \Hirtz\Skeleton\Base\Module
{
    /**
     * @var string[] containing the allowed file extensions
     */
    public array $allowedExtensions = ['gif', 'jpg', 'jpeg', 'png', 'svg'];

    /**
     * @var bool whether uploads are rewritten upright by their EXIF orientation, see {@see \Hirtz\Media\Models\File::orientImage()}
     */
    public bool $autorotateImages = true;

    /**
     * @var int the AVIF quality of a transformation that names none, see {@see Transformation::avifQuality()}
     */
    public int $avifQuality = 50;

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
     * @var bool whether folders can be renamed. Always off on a remote file system (a `webroot` naming a stream
     * wrapper such as `s3://`), which renames objects, not prefixes: the database would follow and the files not.
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
    public array $assetClasses = [];

    /**
     * @var list<class-string<ActiveRecord>> the records `file/references` rebuilds the file references of, beside
     * the asset classes; the save keeps them current without it
     */
    public array $fileReferenceModelClasses = [];

    /**
     * @var list<Closure(File): list<CustomAttribute>> custom attributes appended to every file's, after its quality attributes
     */
    public array $fileCustomAttributes = [];

    /**
     * @var int the number of files a file custom attribute offers in a select, above which it is an autocomplete
     * @see \Hirtz\Media\Models\CustomAttributes\FileCustomAttribute::selectLimit()
     */
    public int $fileSelectLimit = 100;

    /**
     * @var int|null|false duration in seconds for caching the folder query. Set to `false` to disable cache.
     * @see FolderCollection::getAll()
     */
    public int|null|false $folderCachedQueryDuration = 0;

    /**
     * @var bool whether an upload is stored in a directory named after its content (`3f9a2c1b/photo.jpg`), so a file
     * never comes back under a URL a cache still holds another one for
     */
    public bool $hashDirectories = true;

    /**
     * @var bool whether an upload keeps its own filename rather than being renamed to a random string, defaults
     * to `true`. A name already taken in the folder is numbered.
     * @see \Hirtz\Media\Models\File::validateFilename()
     */
    public bool $keepFilename = true;

    /**
     * @var bool whether a file of the same name on disk is overwritten instead of the upload being numbered. A name
     * another record holds is still refused: the record would share its file, and the unique key refuses it anyway.
     */
    public bool $overwriteFiles = false;

    /**
     * @var class-string<ImageProcessor>|array{class: class-string<ImageProcessor>}|ImageProcessor reads, transforms
     * and writes the images; a configuration array passes another Intervention driver to its constructor
     */
    public string|array|ImageProcessor $imageProcessor = ImageProcessor::class;

    /**
     * @var array<string, class-string<ImageGeneratorInterface>|array{class: class-string<ImageGeneratorInterface>}|ImageGeneratorInterface|null>
     * the services MCP `generate_image` creates images with, by name; each is offered once a key is there, the site's
     * in `params` or the user's own with {@see static::$imageGeneratorKeys}, and `null` removes one
     */
    public array $imageGenerators = [
        'openai' => OpenAiImageGenerator::class,
        'google' => GoogleImageGenerator::class,
        'stability' => StabilityImageGenerator::class,
        'firefly' => FireflyImageGenerator::class,
    ];

    /**
     * @var string whose key pays for an image: `site` (the generator's `apiKey`), `user` (each user's own, from the
     * account's API keys) or `user-then-site`, which offers the API keys tab without a site key
     */
    public string $imageGeneratorKeys = 'user-then-site';

    /**
     * @var int images a user may generate per day on the site's key
     */
    public int $imageGeneratorDailyLimit = 20;

    /**
     * @var int the JPEG quality of a transformation that names none, see {@see Transformation::jpegQuality()}
     */
    public int $jpegQuality = 75;

    /**
     * @var int|null the longest side of the preview MCP `get_record` shows of an image, `null` for none
     */
    public ?int $mcpPreviewSize = 1024;

    /**
     * @var int the resolution in pixels per inch of a transformation that names none
     */
    public int $resolution = 72;

    /**
     * @var bool whether a transformation is written without the source's EXIF, XMP and IPTC data, keeping its colour
     * profile: smaller files, no camera or GPS data in public derivatives, and ImageMagick 6 cannot encode a WebP from a
     * source carrying a broken EXIF block.
     */
    public bool $stripMetadata = true;

    /**
     * @var int the WebP quality of a transformation that names none, see {@see Transformation::webpQuality()}
     */
    public int $webpQuality = 80;

    /**
     * @var list<string> containing file extensions which can be transformed and modified to
     * `transformationExtensions` file types.
     */
    public array $transformableImageExtensions = ['jpg', 'jpeg', 'png'];

    /**
     * @var list<string> the extensions {@see File::isVideo()} answers for, which `yii2-media-video` sets
     */
    public array $videoExtensions = ['mp4', 'ogg', 'webm'];

    /**
     * @var list<string> additional file transformation extensions.
     */
    public array $transformationExtensions = ['avif', 'webp'];

    /**
     * @var list<string>|null the formats a file sets its own transformation quality for, `null` for the first one
     * {@see getTransformationExtensions()} answers
     */
    public ?array $transformationQualityExtensions = null;

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

    /**
     * The names, in the same order. Read from the keys, a numeric name such as `335` would be an `int`.
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

    /**
     * The format an admin thumbnail is linked in, and the one a file's posted preview is written in.
     */
    public function getPreviewExtension(): string
    {
        $extensions = $this->getTransformationExtensions();
        return in_array('avif', $extensions, true) ? 'avif' : ($extensions[0] ?? 'jpg');
    }

    /**
     * The configured `transformationExtensions` this server can encode, so a site without an AVIF encoder falls back
     * to the next format instead of linking images that fail.
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
     * @return list<string> the configured {@see $transformationQualityExtensions} this server can encode
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
