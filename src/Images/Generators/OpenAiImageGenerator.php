<?php

declare(strict_types=1);

namespace Hirtz\Media\Images\Generators;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use JsonException;
use Override;
use Yii;

class OpenAiImageGenerator implements ImageGeneratorInterface
{
    public ?string $apiKey = null;
    public string $model = 'gpt-image-1';

    /**
     * @var string `low`, `medium` or `high`, which sets the price of an image as much as its look
     */
    public string $quality = 'medium';

    public string $baseUrl = 'https://api.openai.com/v1';
    public int $connectTimeout = 5;

    /**
     * @var int seconds; an image takes the service half a minute or more
     */
    public int $timeout = 120;

    /**
     * @var ClientInterface|null the test seam: a client built over a `MockHandler`
     */
    public ?ClientInterface $client = null;

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
    public function getKeyUrl(): ?string
    {
        return 'https://platform.openai.com/api-keys';
    }

    #[Override]
    public function getApiKey(): ?string
    {
        return $this->apiKey ?: null;
    }

    #[Override]
    public function generate(string $prompt, string $aspect, string $apiKey): GeneratedImage
    {
        $data = $this->request('POST', 'images/generations', $apiKey, [
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
        ]);

        $image = $data['data'][0]['b64_json'] ?? null;
        $content = is_string($image) ? base64_decode($image, true) : false;

        if (!$content) {
            throw new ImageGeneratorException('The image service answered no image.');
        }

        return new GeneratedImage($content, 'jpg');
    }

    #[Override]
    public function verify(string $apiKey): bool
    {
        try {
            $this->request('GET', 'models', $apiKey);
        } catch (ImageGeneratorException) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<array-key, mixed>
     * @throws ImageGeneratorException
     */
    protected function request(string $method, string $path, string $apiKey, ?array $body = null): array
    {
        try {
            $response = $this->getClient()->request($method, "$this->baseUrl/$path", array_filter([
                RequestOptions::HEADERS => ['Authorization' => "Bearer $apiKey"],
                RequestOptions::JSON => $body,
                RequestOptions::CONNECT_TIMEOUT => $this->connectTimeout,
                RequestOptions::TIMEOUT => $this->timeout,
                RequestOptions::HTTP_ERRORS => false,
            ], fn (mixed $value): bool => $value !== null));
        } catch (GuzzleException $exception) {
            Yii::warning("The image service failed: {$exception->getMessage()}", __METHOD__);
            throw new ImageGeneratorException('The image service could not be reached.');
        }

        try {
            $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $data = null;
        }

        $data = is_array($data) ? $data : [];

        if ($response->getStatusCode() !== 200) {
            $message = $data['error']['message'] ?? null;
            throw new ImageGeneratorException(match ($response->getStatusCode()) {
                401 => 'The image service refused the key.',
                429 => 'The image service is busy or the key has run out of credit.',
                default => is_string($message) ? $message : "The image service answered {$response->getStatusCode()}.",
            });
        }

        return $data;
    }

    protected function getClient(): ClientInterface
    {
        return $this->client ??= new Client();
    }
}
