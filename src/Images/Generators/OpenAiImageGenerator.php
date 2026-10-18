<?php

declare(strict_types=1);

namespace Hirtz\Media\Images\Generators;

use GuzzleHttp\RequestOptions;
use Override;

class OpenAiImageGenerator extends ImageGenerator
{
    public string $model = 'gpt-image-1';

    /**
     * @var string `low`, `medium` or `high`, which sets the price of an image as much as its look
     */
    public string $quality = 'medium';

    public string $baseUrl = 'https://api.openai.com/v1';

    #[Override]
    public function getName(): string
    {
        return 'openai';
    }

    #[Override]
    public function getLabel(): string
    {
        return 'OpenAI';
    }

    #[Override]
    public function getDescription(): string
    {
        return 'Follows long, detailed prompts closely and renders short text well.';
    }

    #[Override]
    public function getKeyUrl(): ?string
    {
        return 'https://platform.openai.com/api-keys';
    }

    #[Override]
    protected function getParamName(): string
    {
        return 'openaiApiKey';
    }

    #[Override]
    public function generate(string $prompt, string $aspect, array $credentials): GeneratedImage
    {
        $apiKey = $this->getKey($credentials);

        $data = $this->decode($this->send('POST', "$this->baseUrl/images/generations", [
            RequestOptions::HEADERS => ['Authorization' => "Bearer $apiKey"],
            RequestOptions::JSON => [
                'model' => $this->model,
                'prompt' => $prompt,
                'n' => 1,
                'quality' => $this->quality,
                'output_format' => 'jpeg',
                'size' => match ($aspect) {
                    self::ASPECT_SQUARE => '1024x1024',
                    self::ASPECT_PORTRAIT => '1024x1536',
                    default => '1536x1024',
                },
            ],
        ]));

        $image = $data['data'][0]['b64_json'] ?? null;
        $content = is_string($image) ? base64_decode($image, true) : false;

        if (!$content) {
            throw new ImageGeneratorException('OpenAI answered no image.');
        }

        return new GeneratedImage($content, 'jpg');
    }

    #[Override]
    public function verify(array $credentials): bool
    {
        try {
            $apiKey = $this->getKey($credentials);
            $this->decode($this->send('GET', "$this->baseUrl/models", [
                RequestOptions::HEADERS => ['Authorization' => "Bearer $apiKey"],
            ]));
        } catch (ImageGeneratorException) {
            return false;
        }

        return true;
    }
}
