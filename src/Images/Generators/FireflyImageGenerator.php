<?php

declare(strict_types=1);

namespace Hirtz\Media\Images\Generators;

use GuzzleHttp\RequestOptions;
use Hirtz\Skeleton\Credentials\CredentialProvider;
use Hirtz\Skeleton\Models\CustomAttributes\TextCustomAttribute;
use Override;
use Yii;

/**
 * Adobe's API authenticates a client by its ID and secret, so the service asks for both. An image is a job, polled
 * until it is done.
 */
class FireflyImageGenerator extends ImageGenerator
{
    public string $tokenUrl = 'https://ims-na1.adobelogin.com/ims/token/v3';
    public string $baseUrl = 'https://firefly-api.adobe.io';
    public string $scope = 'openid,AdobeID,session,additional_info,read_organizations,firefly_api,ff_apis';

    /**
     * @var float seconds between two polls of a job
     */
    public float $pollInterval = 2;

    #[Override]
    public function getName(): string
    {
        return 'firefly';
    }

    #[Override]
    public function getLabel(): string
    {
        return 'Adobe Firefly';
    }

    #[Override]
    public function getDescription(): string
    {
        return 'Trained on licensed content, so safest for commercial use; good for marketing imagery.';
    }

    #[Override]
    public function getKeyUrl(): ?string
    {
        return 'https://developer.adobe.com/console';
    }

    public ?string $clientId = null;
    public ?string $clientSecret = null;

    #[Override]
    protected function getParamName(): string
    {
        return 'fireflyClientSecret';
    }

    #[Override]
    public function getCredentialFields(): array
    {
        return [
            TextCustomAttribute::make('client_id')
                ->label(Yii::t('media', 'IMAGE_GENERATOR_CLIENT_ID_LABEL'))
                ->required(),
            CredentialProvider::createSecretField('client_secret', Yii::t('media', 'IMAGE_GENERATOR_CLIENT_SECRET_LABEL')),
        ];
    }

    /**
     * `params.fireflyClientId` and `params.fireflyClientSecret` when not configured.
     */
    #[Override]
    public function getCredentials(): ?array
    {
        $clientId = $this->clientId ?: (Yii::$app->params['fireflyClientId'] ?? null);
        $clientSecret = $this->clientSecret ?: (Yii::$app->params['fireflyClientSecret'] ?? null);

        return is_string($clientId) && $clientId !== '' && is_string($clientSecret) && $clientSecret !== ''
            ? ['client_id' => $clientId, 'client_secret' => $clientSecret]
            : null;
    }

    #[Override]
    public function generate(string $prompt, string $aspect, array $credentials): GeneratedImage
    {
        $clientId = $this->getKey($credentials, 'client_id');

        $headers = [
            'Authorization' => 'Bearer ' . $this->getAccessToken($credentials),
            'x-api-key' => $clientId,
            'Accept' => 'application/json',
        ];

        $job = $this->decode($this->send('POST', "$this->baseUrl/v3/images/generate-async", [
            RequestOptions::HEADERS => $headers,
            RequestOptions::JSON => [
                'prompt' => $prompt,
                'numVariations' => 1,
                'size' => match ($aspect) {
                    self::ASPECT_SQUARE => ['width' => 2048, 'height' => 2048],
                    self::ASPECT_PORTRAIT => ['width' => 1792, 'height' => 2304],
                    default => ['width' => 2304, 'height' => 1792],
                },
            ],
        ]));

        $statusUrl = $job['statusUrl'] ?? null;

        if (!is_string($statusUrl) || !str_starts_with($statusUrl, 'https://')) {
            throw new ImageGeneratorException('Adobe Firefly answered no job.');
        }

        $deadline = microtime(true) + $this->timeout;

        do {
            $status = $this->decode($this->send('GET', $statusUrl, [RequestOptions::HEADERS => $headers]));

            if (($status['status'] ?? null) === 'succeeded') {
                return $this->download($status);
            }

            if (($status['status'] ?? null) === 'failed') {
                throw new ImageGeneratorException($this->getErrorMessage($status) ?? 'Adobe Firefly could not create the image.');
            }

            usleep((int)($this->pollInterval * 1000000));
        } while (microtime(true) < $deadline);

        throw new ImageGeneratorException('Adobe Firefly did not finish the image in time.');
    }

    #[Override]
    public function verify(array $credentials): bool
    {
        try {
            $this->getAccessToken($credentials);
        } catch (ImageGeneratorException) {
            return false;
        }

        return true;
    }

    /**
     * @param array<array-key, mixed> $status
     * @throws ImageGeneratorException
     */
    protected function download(array $status): GeneratedImage
    {
        $url = $status['result']['outputs'][0]['image']['url'] ?? null;

        if (!is_string($url) || !str_starts_with($url, 'https://')) {
            throw new ImageGeneratorException('Adobe Firefly answered no image.');
        }

        $response = $this->send('GET', $url);
        $content = (string)$response->getBody();

        if ($response->getStatusCode() !== 200 || $content === '') {
            throw new ImageGeneratorException('The image of Adobe Firefly could not be downloaded.');
        }

        return new GeneratedImage($content, str_contains($response->getHeaderLine('Content-Type'), 'png') ? 'png' : 'jpg');
    }

    /**
     * @param array<string, string> $credentials
     * @throws ImageGeneratorException
     */
    protected function getAccessToken(array $credentials): string
    {
        $clientId = $this->getKey($credentials, 'client_id');
        $clientSecret = $this->getKey($credentials, 'client_secret');

        $data = $this->decode($this->send('POST', $this->tokenUrl, [
            RequestOptions::FORM_PARAMS => [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => $this->scope,
            ],
        ]));

        $token = $data['access_token'] ?? null;

        if (!is_string($token) || $token === '') {
            throw new ImageGeneratorException('Adobe Firefly refused the key.');
        }

        return $token;
    }

}
