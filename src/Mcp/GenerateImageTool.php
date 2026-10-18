<?php

declare(strict_types=1);

namespace Hirtz\Media\Mcp;

use Hirtz\Media\Images\Generators\ImageGeneratorException;
use Hirtz\Media\Images\Generators\ImageGeneratorInterface;
use Hirtz\Media\Models\Asset;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Skeleton\Caching\CacheCounter;
use Hirtz\Skeleton\Mcp\Tools\ToolException;
use Hirtz\Skeleton\Models\UserCredential;
use Hirtz\Skeleton\Web\ContentUploadedFile;
use Hirtz\Skeleton\Web\User as WebUser;
use Override;
use yii\helpers\Inflector;

/**
 * Generates an image on the server and attaches it as `attach_file` does: the image never passes through the model,
 * which could not copy a megabyte of it into a call. The asset starts as a draft.
 */
class GenerateImageTool extends AttachFileTool
{
    use ModuleTrait;

    /**
     * @var bool|null whether the last image was paid by the site's key rather than the user's own
     */
    private ?bool $isSiteKey = null;

    #[Override]
    public function getName(): string
    {
        return 'generate_image';
    }

    #[Override]
    public function getTitle(): string
    {
        return 'Generate an image';
    }

    #[Override]
    public function getDescription(): string
    {
        $providers = array_map(
            fn (ImageGeneratorInterface $generator): string => "\"{$generator->getName()}\" ({$generator->getLabel()}): {$generator->getDescription()}",
            $this->getAvailableGenerators(),
        );

        return 'Generates an image from a "prompt" and attaches it to a record as a draft, e.g. a header image or an '
            . 'illustration for a section. Describe subject, style and mood; ask for no text in the image. Look at the '
            . 'result with get_record on the file and write its alt text. Every image is charged to the site or the '
            . 'user\'s own key, which the result names. Providers, the first by default: ' . implode(' ', $providers);
    }

    #[Override]
    public function getInputSchema(): array
    {
        $schema = parent::getInputSchema();
        unset($schema['properties']['url'], $schema['properties']['content'], $schema['properties']['filename']);

        $schema['properties'] = [
            ...$schema['properties'],
            'prompt' => ['type' => 'string', 'maxLength' => 4000],
            'provider' => ['type' => 'string', 'enum' => array_keys($this->getAvailableGenerators())],
            'aspect' => [
                'type' => 'string',
                'enum' => [
                    ImageGeneratorInterface::ASPECT_LANDSCAPE,
                    ImageGeneratorInterface::ASPECT_SQUARE,
                    ImageGeneratorInterface::ASPECT_PORTRAIT,
                ],
                'default' => ImageGeneratorInterface::ASPECT_LANDSCAPE,
            ],
        ];

        $schema['required'] = ['resource', 'id', 'prompt'];

        return $schema;
    }

    #[Override]
    public function isOpenWorld(): bool
    {
        return true;
    }

    /**
     * A user without a key for any service is offered no tool, rather than one that always fails.
     */
    #[Override]
    public function isAvailable(): bool
    {
        return parent::isAvailable() && $this->getAvailableGenerators() !== [];
    }

    #[Override]
    protected function run(array $arguments): array
    {
        $attributes = is_array($arguments['attributes'] ?? null) ? $arguments['attributes'] : [];
        $arguments['attributes'] = ['status' => Asset::STATUS_DRAFT, ...$attributes];
        $result = parent::run($arguments);

        if ($this->isSiteKey) {
            CacheCounter::increment($this->getLimitKey(), 86400);
        }

        return [...$result, 'billed_to' => $this->isSiteKey ? 'the site' : 'your own key'];
    }

    #[Override]
    protected function getUploadConfig(array $arguments): array
    {
        $generators = $this->getAvailableGenerators();
        $name = $arguments['provider'] ?? array_key_first($generators);
        $generator = is_string($name) ? ($generators[$name] ?? null) : null;

        if ($generator === null) {
            throw new ToolException('No key for this image service: add your own under your account\'s API keys.');
        }

        $apiKey = $this->getApiKey($generator) ?? throw new ToolException("Add your own key for {$generator->getLabel()} under your account's API keys.");

        if ($this->isSiteKey && CacheCounter::get($this->getLimitKey()) >= static::getModule()->imageGeneratorDailyLimit) {
            throw new ToolException('The daily limit of ' . static::getModule()->imageGeneratorDailyLimit . ' images is reached.');
        }

        $prompt = trim((string)$arguments['prompt']);
        $aspect = match ($arguments['aspect'] ?? null) {
            ImageGeneratorInterface::ASPECT_SQUARE => ImageGeneratorInterface::ASPECT_SQUARE,
            ImageGeneratorInterface::ASPECT_PORTRAIT => ImageGeneratorInterface::ASPECT_PORTRAIT,
            default => ImageGeneratorInterface::ASPECT_LANDSCAPE,
        };

        try {
            $image = $generator->generate($prompt, $aspect, $apiKey);
        } catch (ImageGeneratorException $exception) {
            throw new ToolException($exception->getMessage());
        }

        return [
            'class' => ContentUploadedFile::class,
            'content' => $image->content,
            'filename' => (Inflector::slug(mb_substr($prompt, 0, 60)) ?: 'image') . ".$image->extension",
        ];
    }

    /**
     * The services there is a key for, the site's or this user's, checked without decrypting a user's key, which
     * would count as a use of it.
     *
     * @return array<string, ImageGeneratorInterface>
     */
    protected function getAvailableGenerators(): array
    {
        $module = static::getModule();
        $generators = $module->getImageGenerators();

        $own = $module->imageGeneratorKeys !== 'site' && $generators
            ? UserCredential::find()
                ->select('provider')
                ->where(['user_id' => WebUser::current()?->getId(), 'provider' => array_keys($generators)])
                ->column()
            : [];

        return array_filter(
            $generators,
            fn (ImageGeneratorInterface $generator): bool => in_array($generator->getName(), $own, true)
                || ($module->imageGeneratorKeys !== 'user' && $generator->getApiKey() !== null),
        );
    }

    /**
     * Sets {@see static::$isSiteKey} as it goes, the key's owner being what the result reports.
     */
    protected function getApiKey(ImageGeneratorInterface $generator): ?string
    {
        $module = static::getModule();
        $userId = (int)WebUser::current()?->getId();

        $own = $module->imageGeneratorKeys !== 'site' && $userId
            ? UserCredential::findSecret($userId, $generator->getName())
            : null;

        $key = $own ?? ($module->imageGeneratorKeys !== 'user' ? $generator->getApiKey() : null);
        $this->isSiteKey = $key !== null && $own === null;

        return $key;
    }

    /**
     * @return array{class-string, int, string}
     */
    private function getLimitKey(): array
    {
        return [self::class, (int)WebUser::current()?->getId(), gmdate('Y-m-d')];
    }
}
