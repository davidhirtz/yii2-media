<?php

declare(strict_types=1);

namespace Hirtz\Media\Images\Generators;

use Hirtz\Skeleton\Models\CustomAttributes\CustomAttribute;

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

    public function getKeyUrl(): ?string;

    /**
     * A single `key` unless the service needs more.
     *
     * @return list<CustomAttribute>
     */
    public function getCredentialFields(): array;

    /**
     * Named as the fields are, `null` when every user brings their own.
     *
     * @return array<string, string>|null
     */
    public function getCredentials(): ?array;

    /**
     * @param self::ASPECT_* $aspect
     * @param array<string, string> $credentials
     * @throws ImageGeneratorException
     */
    public function generate(string $prompt, string $aspect, array $credentials): GeneratedImage;

    /**
     * @param array<string, string> $credentials
     */
    public function verify(array $credentials): bool;
}
