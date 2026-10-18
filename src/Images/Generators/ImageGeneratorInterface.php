<?php

declare(strict_types=1);

namespace Hirtz\Media\Images\Generators;

interface ImageGeneratorInterface
{
    final public const string ASPECT_SQUARE = 'square';
    final public const string ASPECT_LANDSCAPE = 'landscape';
    final public const string ASPECT_PORTRAIT = 'portrait';

    /**
     * The name a user's own key is stored under, see {@see \Hirtz\Skeleton\Models\UserCredential}.
     */
    public function getName(): string;

    public function getLabel(): string;

    /**
     * What the service is good at, in a sentence an agent chooses between services by.
     */
    public function getDescription(): string;

    /**
     * Where a user gets a key of their own.
     */
    public function getKeyUrl(): ?string;

    /**
     * The site's key, `null` when every user brings their own.
     */
    public function getApiKey(): ?string;

    /**
     * @param self::ASPECT_* $aspect
     * @throws ImageGeneratorException
     */
    public function generate(string $prompt, string $aspect, string $apiKey): GeneratedImage;

    public function verify(string $apiKey): bool;
}
