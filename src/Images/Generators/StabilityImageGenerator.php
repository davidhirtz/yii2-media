<?php

declare(strict_types=1);

namespace Hirtz\Media\Images\Generators;

use GuzzleHttp\RequestOptions;
use Override;

class StabilityImageGenerator extends ImageGenerator
{
    /**
     * @var string `core`, `ultra` or `sd3`, the endpoint under `stable-image/generate`
     */
    public string $model = 'core';

    public string $baseUrl = 'https://api.stability.ai';

    #[Override]
    public function getName(): string
    {
        return 'stability';
    }

    #[Override]
    public function getLabel(): string
    {
        return 'Stability AI';
    }

    #[Override]
    public function getDescription(): string
    {
        return 'Inexpensive, with a wide range of artistic styles.';
    }

    #[Override]
    public function getKeyUrl(): ?string
    {
        return 'https://platform.stability.ai/account/keys';
    }

    #[Override]
    protected function getParamName(): string
    {
        return 'stabilityApiKey';
    }

    #[Override]
    public function generate(string $prompt, string $aspect, array $credentials): GeneratedImage
    {
        $apiKey = $this->getKey($credentials);

        $response = $this->send('POST', "$this->baseUrl/v2beta/stable-image/generate/$this->model", [
            RequestOptions::HEADERS => ['Authorization' => "Bearer $apiKey", 'Accept' => 'image/*'],
            RequestOptions::MULTIPART => [
                ['name' => 'prompt', 'contents' => $prompt],
                ['name' => 'output_format', 'contents' => 'jpeg'],
                ['name' => 'aspect_ratio', 'contents' => match ($aspect) {
                    self::ASPECT_SQUARE => '1:1',
                    self::ASPECT_PORTRAIT => '2:3',
                    default => '3:2',
                }],
            ],
        ]);

        // An error answers JSON whatever was asked for
        if ($response->getStatusCode() !== 200) {
            $this->decode($response);
        }

        $content = (string)$response->getBody();

        if ($content === '') {
            throw new ImageGeneratorException('Stability AI answered no image.');
        }

        return new GeneratedImage($content, 'jpg');
    }

    #[Override]
    public function verify(array $credentials): bool
    {
        try {
            $apiKey = $this->getKey($credentials);
            $this->decode($this->send('GET', "$this->baseUrl/v1/user/account", [
                RequestOptions::HEADERS => ['Authorization' => "Bearer $apiKey"],
            ]));
        } catch (ImageGeneratorException) {
            return false;
        }

        return true;
    }

    #[Override]
    protected function getErrorMessage(array $data): ?string
    {
        $errors = $data['errors'] ?? null;

        return is_array($errors) && is_string($errors[0] ?? null)
            ? "Stability AI: {$errors[0]}"
            : parent::getErrorMessage($data);
    }
}
