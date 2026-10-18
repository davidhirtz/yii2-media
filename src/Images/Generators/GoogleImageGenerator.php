<?php

declare(strict_types=1);

namespace Hirtz\Media\Images\Generators;

use GuzzleHttp\RequestOptions;
use Override;

/**
 * Gemini's image models through `generateContent`; Imagen is no longer offered in the Gemini API.
 */
class GoogleImageGenerator extends ImageGenerator
{
    public string $model = 'gemini-2.5-flash-image';
    public string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    #[Override]
    public function getName(): string
    {
        return 'google';
    }

    #[Override]
    public function getLabel(): string
    {
        return 'Google Gemini';
    }

    #[Override]
    public function getDescription(): string
    {
        return 'Photorealistic scenes and people, quick and inexpensive.';
    }

    #[Override]
    public function getKeyUrl(): ?string
    {
        return 'https://aistudio.google.com/apikey';
    }

    #[Override]
    protected function getParamName(): string
    {
        return 'geminiApiKey';
    }

    #[Override]
    public function generate(string $prompt, string $aspect, string $apiKey): GeneratedImage
    {
        $data = $this->decode($this->send('POST', "$this->baseUrl/models/$this->model:generateContent", [
            RequestOptions::HEADERS => ['x-goog-api-key' => $apiKey],
            RequestOptions::JSON => [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'responseModalities' => ['IMAGE'],
                    'imageConfig' => [
                        'aspectRatio' => match ($aspect) {
                            self::ASPECT_SQUARE => '1:1',
                            self::ASPECT_PORTRAIT => '2:3',
                            default => '3:2',
                        },
                    ],
                ],
            ],
        ]));

        $parts = $data['candidates'][0]['content']['parts'] ?? [];

        foreach (is_array($parts) ? $parts : [] as $part) {
            $inline = is_array($part) ? ($part['inlineData'] ?? $part['inline_data'] ?? null) : null;
            $content = is_array($inline) && is_string($inline['data'] ?? null) ? base64_decode($inline['data'], true) : false;

            if ($content) {
                $mimeType = $inline['mimeType'] ?? $inline['mime_type'] ?? null;
                return new GeneratedImage($content, $mimeType === 'image/png' ? 'png' : 'jpg');
            }
        }

        throw new ImageGeneratorException('Google Gemini answered no image, possibly because it declined the prompt.');
    }

    #[Override]
    public function verify(string $apiKey): bool
    {
        try {
            $this->decode($this->send('GET', "$this->baseUrl/models", [
                RequestOptions::HEADERS => ['x-goog-api-key' => $apiKey],
            ]));
        } catch (ImageGeneratorException) {
            return false;
        }

        return true;
    }
}
