<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Images\Generators;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Hirtz\Media\Images\Generators\FireflyImageGenerator;
use Hirtz\Media\Images\Generators\GoogleImageGenerator;
use Hirtz\Media\Images\Generators\ImageGenerator;
use Hirtz\Media\Images\Generators\ImageGeneratorException;
use Hirtz\Media\Images\Generators\StabilityImageGenerator;
use Hirtz\Media\Test\TestCase;
use Psr\Http\Message\RequestInterface;
use Yii;

/**
 * The requests as the services document them, against recorded answers; none leaves the test.
 */
class HttpImageGeneratorsTest extends TestCase
{
    /**
     * @var list<RequestInterface>
     */
    private array $requests = [];

    public function testGoogleAsksGeminiForAnImageAndReadsTheInlineData(): void
    {
        $generator = $this->mock(new GoogleImageGenerator(), new Response(200, [], json_encode([
            'candidates' => [['content' => ['parts' => [
                ['text' => 'Hier ist Ihr Bild 🎉'],
                ['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode('PNG-Bytes')]],
            ]]]],
        ], JSON_THROW_ON_ERROR)));

        $image = $generator->generate('Ein Leuchtturm – Привет', 'portrait', 'g-key');

        self::assertSame('PNG-Bytes', $image->content);
        self::assertSame('png', $image->extension);

        $request = $this->requests[0] ?? self::fail('No request');
        $body = json_decode((string)$request->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertStringEndsWith('/models/gemini-2.5-flash-image:generateContent', (string)$request->getUri());
        self::assertSame('g-key', $request->getHeaderLine('x-goog-api-key'));
        self::assertSame('2:3', $body['generationConfig']['imageConfig']['aspectRatio'] ?? null);
        self::assertSame('Ein Leuchtturm – Привет', $body['contents'][0]['parts'][0]['text'] ?? null);
    }

    public function testGoogleWithoutAnImageSaysSo(): void
    {
        $generator = $this->mock(new GoogleImageGenerator(), new Response(200, [], '{"candidates":[{"content":{"parts":[{"text":"Nein."}]}}]}'));

        $this->expectException(ImageGeneratorException::class);
        $generator->generate('x', 'square', 'g-key');
    }

    public function testStabilityPostsAFormAndReceivesTheBytes(): void
    {
        $generator = $this->mock(new StabilityImageGenerator(), new Response(200, ['Content-Type' => 'image/jpeg'], 'JPEG-Bytes'));
        $image = $generator->generate('Ein Leuchtturm', 'square', 's-key');

        self::assertSame('JPEG-Bytes', $image->content);

        $request = $this->requests[0] ?? self::fail('No request');
        $body = (string)$request->getBody();

        self::assertStringEndsWith('/v2beta/stable-image/generate/core', (string)$request->getUri());
        self::assertSame('Bearer s-key', $request->getHeaderLine('Authorization'));
        self::assertSame('image/*', $request->getHeaderLine('Accept'));
        self::assertStringContainsString("name=\"aspect_ratio\"\r\nContent-Length: 3\r\n\r\n1:1", $body);
    }

    public function testStabilityNamesItsError(): void
    {
        $generator = $this->mock(new StabilityImageGenerator(), new Response(400, [], '{"name":"bad_request","errors":["prompt: too long"]}'));

        $this->expectExceptionMessage('Stability AI: prompt: too long');
        $generator->generate('x', 'square', 's-key');
    }

    public function testFireflyTakesATokenPollsTheJobAndDownloadsTheImage(): void
    {
        $generator = $this->mock(
            new FireflyImageGenerator(),
            new Response(200, [], '{"access_token":"ff-token"}'),
            new Response(202, [], '{"jobId":"urn:ff:jobs:1","statusUrl":"https://firefly-api.adobe.io/v3/status/urn:ff:jobs:1"}'),
            new Response(200, [], '{"status":"running"}'),
            new Response(200, [], '{"status":"succeeded","result":{"outputs":[{"image":{"url":"https://images.example/1.jpg"}}]}}'),
            new Response(200, ['Content-Type' => 'image/jpeg'], 'JPEG-Bytes'),
        );

        $image = $generator->generate('Ein Leuchtturm', 'landscape', 'client-id:client-secret');

        self::assertSame('JPEG-Bytes', $image->content);
        self::assertCount(5, $this->requests);

        self::assertStringContainsString('client_secret=client-secret', (string)$this->requests[0]->getBody());
        self::assertSame('client-id', $this->requests[1]->getHeaderLine('x-api-key'));
        self::assertSame('Bearer ff-token', $this->requests[1]->getHeaderLine('Authorization'));
        self::assertStringContainsString('"width":2304', (string)$this->requests[1]->getBody());
        self::assertSame('https://images.example/1.jpg', (string)$this->requests[4]->getUri());
    }

    public function testAFireflyKeyIsIdAndSecret(): void
    {
        $generator = $this->mock(new FireflyImageGenerator());

        $this->expectExceptionMessage('client ID and secret joined by a colon');
        $generator->generate('x', 'square', 'only-one-part');
    }

    public function testTheSitesKeysComeFromParams(): void
    {
        Yii::$app->params['geminiApiKey'] = 'g-site';
        Yii::$app->params['fireflyClientId'] = 'ff-id';
        Yii::$app->params['fireflyClientSecret'] = 'ff-secret';

        self::assertSame('g-site', (new GoogleImageGenerator())->getApiKey());
        self::assertSame('ff-id:ff-secret', (new FireflyImageGenerator())->getApiKey());
        self::assertNull((new StabilityImageGenerator())->getApiKey());
    }

    /**
     * @template T of ImageGenerator
     * @param T $generator
     * @return T
     */
    private function mock(ImageGenerator $generator, Response ...$responses): ImageGenerator
    {
        $this->requests = [];
        $stack = HandlerStack::create(new MockHandler(array_values($responses)));
        $stack->push(Middleware::mapRequest(function (RequestInterface $request): RequestInterface {
            $this->requests[] = $request;
            return $request;
        }));

        $generator->client = new Client(['handler' => $stack]);

        if ($generator instanceof FireflyImageGenerator) {
            $generator->pollInterval = 0;
        }

        return $generator;
    }
}
