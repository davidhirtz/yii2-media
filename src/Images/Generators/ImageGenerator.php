<?php

declare(strict_types=1);

namespace Hirtz\Media\Images\Generators;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Hirtz\Skeleton\Credentials\CredentialProvider;
use JsonException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Yii;

/**
 * A service called over HTTP with a key of the site's, read from `params` when it is not configured, or of the user's.
 */
abstract class ImageGenerator implements ImageGeneratorInterface
{
    /**
     * @var string|null the site's key, `params[static::getParamName()]` when it is not set
     */
    public ?string $apiKey = null;

    public int $connectTimeout = 5;

    /**
     * @var int seconds; an image takes a service half a minute or more
     */
    public int $timeout = 120;

    /**
     * @var ClientInterface|null the test seam: a client built over a `MockHandler`
     */
    public ?ClientInterface $client = null;

    /**
     * The `params` key the site's key is read from.
     */
    abstract protected function getParamName(): string;

    #[Override]
    public function getCredentialFields(): array
    {
        return [CredentialProvider::createSecretField('key', Yii::t('skeleton', 'CREDENTIAL_SECRET_LABEL'))];
    }

    #[Override]
    public function getCredentials(): ?array
    {
        $apiKey = $this->apiKey ?: (Yii::$app->params[$this->getParamName()] ?? null);
        return is_string($apiKey) && $apiKey !== '' ? ['key' => $apiKey] : null;
    }

    /**
     * @param array<string, string> $credentials
     * @throws ImageGeneratorException
     */
    protected function getKey(array $credentials, string $name = 'key'): string
    {
        $value = $credentials[$name] ?? '';
        return $value !== '' ? $value : throw new ImageGeneratorException("{$this->getLabel()} has no \"$name\".");
    }

    /**
     * @param array<string, mixed> $options
     * @throws ImageGeneratorException
     */
    protected function send(string $method, string $url, array $options = []): ResponseInterface
    {
        try {
            return $this->getClient()->request($method, $url, [
                RequestOptions::CONNECT_TIMEOUT => $this->connectTimeout,
                RequestOptions::TIMEOUT => $this->timeout,
                RequestOptions::HTTP_ERRORS => false,
                ...$options,
            ]);
        } catch (GuzzleException $exception) {
            Yii::warning("{$this->getLabel()} failed: {$exception->getMessage()}", __METHOD__);
            throw new ImageGeneratorException("{$this->getLabel()} could not be reached.");
        }
    }

    /**
     * The decoded body of a successful response.
     *
     * @return array<array-key, mixed>
     * @throws ImageGeneratorException
     */
    protected function decode(ResponseInterface $response): array
    {
        try {
            $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $data = null;
        }

        $data = is_array($data) ? $data : [];

        if ($response->getStatusCode() >= 300) {
            throw new ImageGeneratorException(match ($response->getStatusCode()) {
                401, 403 => "{$this->getLabel()} refused the key.",
                402, 429 => "{$this->getLabel()} is busy or the key has run out of credit.",
                default => $this->getErrorMessage($data) ?? "{$this->getLabel()} answered {$response->getStatusCode()}.",
            });
        }

        return $data;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    protected function getErrorMessage(array $data): ?string
    {
        $message = $data['error']['message'] ?? $data['message'] ?? null;
        return is_string($message) && $message !== '' ? "{$this->getLabel()}: $message" : null;
    }

    protected function getClient(): ClientInterface
    {
        return $this->client ??= new Client();
    }
}
