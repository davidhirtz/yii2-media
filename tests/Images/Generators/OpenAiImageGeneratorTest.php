<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Images\Generators;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Hirtz\Media\Images\Generators\ImageGeneratorException;
use Hirtz\Media\Images\Generators\OpenAiImageGenerator;
use Hirtz\Media\Test\TestCase;
use Psr\Http\Message\RequestInterface;

class OpenAiImageGeneratorTest extends TestCase
{
    /**
     * @var list<RequestInterface>
     */
    private array $requests = [];

    public function testAnImageIsRequestedInTheAspectsSizeAndDecoded(): void
    {
        $generator = $this->createGenerator(new Response(200, [], json_encode(['data' => [['b64_json' => base64_encode('JPEG-Bytes')]]], JSON_THROW_ON_ERROR)));
        $image = $generator->generate('Ein Leuchtturm – Привет 🎉', 'portrait', ['key' => 'sk-key']);

        self::assertSame('JPEG-Bytes', $image->content);
        self::assertSame('jpg', $image->extension);

        $request = $this->requests[0] ?? self::fail('No request');
        $body = json_decode((string)$request->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('Bearer sk-key', $request->getHeaderLine('Authorization'));
        self::assertSame('1024x1536', $body['size'] ?? null);
        self::assertSame('Ein Leuchtturm – Привет 🎉', $body['prompt'] ?? null);
    }

    public function testARefusedKeyIsWordedForTheUser(): void
    {
        $generator = $this->createGenerator(new Response(401, [], '{"error":{"message":"Incorrect API key"}}'));

        $this->expectException(ImageGeneratorException::class);
        $this->expectExceptionMessage('OpenAI refused the key.');

        $generator->generate('x', 'square', ['key' => 'wrong']);
    }

    public function testAKeyIsVerifiedByListingTheModels(): void
    {
        self::assertTrue($this->createGenerator(new Response(200, [], '{"data":[]}'))->verify(['key' => 'sk-key']));
        self::assertFalse($this->createGenerator(new Response(401, [], '{}'))->verify(['key' => 'wrong']));
        $request = $this->requests[0] ?? self::fail('No request');
        self::assertSame('GET', $request->getMethod());
    }

    private function createGenerator(Response ...$responses): OpenAiImageGenerator
    {
        $this->requests = [];
        $stack = HandlerStack::create(new MockHandler(array_values($responses)));
        $stack->push(Middleware::mapRequest(function (RequestInterface $request): RequestInterface {
            $this->requests[] = $request;
            return $request;
        }));

        $generator = new OpenAiImageGenerator();
        $generator->client = new Client(['handler' => $stack]);

        return $generator;
    }
}
