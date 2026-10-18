<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Mcp;

use Hirtz\Media\Models\File;
use Hirtz\Media\Test\TestCase;
use Hirtz\Skeleton\Mcp\Mcp;
use Hirtz\Skeleton\Mcp\SchemaBuilder;

class FileResourceTest extends TestCase
{
    public function testTheCropIsNotWritable(): void
    {
        $resource = Mcp::getComponent()->getResource('file') ?? self::fail('No file resource');
        $attributes = (new SchemaBuilder($resource))->getWritableAttributes(File::create());

        self::assertContains('alt_text', $attributes);

        foreach (['upload', 'width', 'height', 'x', 'y', 'angle'] as $attribute) {
            self::assertNotContains($attribute, $attributes);
        }
    }
}
