<?php

declare(strict_types=1);

namespace Hirtz\Media\Images\Generators;

use GuzzleHttp\RequestOptions;
use Override;
use Yii;

/**
 * Adobe's API authenticates a client by its ID and secret, so a key here is the two joined by a colon,
 * `clientId:clientSecret`. An image is a job, polled until it is done.
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

    #[Override]
    protected function getParamName(): string
    {
        return 'fireflyApiKey';
    }

    /**
     * The site's key may also be configured as `fireflyClientId` and `fireflyClientSecret`.
     */
    #[Override]
    public function getApiKey(): ?string
    {
        $params = Yii::$app->params;

        if (!$this->apiKey && is_string($params['fireflyClientId'] ?? null) && is_string($params['fireflyClientSecret'] ?? null)) {
            return "{$params['fireflyClientId']}:{$params['fireflyClientSecret']}";
        }

        return parent::getApiKey();
    }

    #[Override]
    public function generate(string $prompt, string $aspect, string $apiKey): GeneratedImage
    {
        [$clientId] = $this->splitKey($apiKey);

        $headers = [
            'Authorization' => 'Bearer ' . $this->getAccessToken($apiKey),
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
    public function verify(string $apiKey): bool
    {
        try {
            $this->getAccessToken($apiKey);
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
     * @throws ImageGeneratorException
     */
    protected function getAccessToken(string $apiKey): string
    {
        [$clientId, $clientSecret] = $this->splitKey($apiKey);

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

    /**
     * @return array{string, string}
     * @throws ImageGeneratorException
     */
    protected function splitKey(string $apiKey): array
    {
        $parts = explode(':', $apiKey, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new ImageGeneratorException('An Adobe Firefly key is the client ID and secret joined by a colon.');
        }

        return [$parts[0], $parts[1]];
    }
}
