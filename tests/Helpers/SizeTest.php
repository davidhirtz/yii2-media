<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Helpers;

use Hirtz\Media\Helpers\Size;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Media\Test\TestCase;
use yii\base\InvalidConfigException;

class SizeTest extends TestCase
{
    use ModuleTrait;

    public function testABareSizeIsItsValue(): void
    {
        $size = Size::value('100vw');

        self::assertFalse($size->isConditional());
        self::assertSame('100vw', (string)$size);
    }

    public function testAnIntegerBreakpointBecomesAMaxWidthQuery(): void
    {
        $size = Size::breakpoint('sm', '100vw');

        self::assertTrue($size->isConditional());
        self::assertSame('(max-width:767px) 100vw', (string)$size);
    }

    public function testAStringBreakpointIsUsedVerbatim(): void
    {
        self::getModule()->breakpoints['print'] = 'print';
        self::assertSame('print 50vw', (string)Size::breakpoint('print', '50vw'));
    }

    public function testAnUndeclaredBreakpointThrows(): void
    {
        $this->expectException(InvalidConfigException::class);
        Size::breakpoint('does-not-exist', '100vw');
    }

    public function testAMediaQueryIsUsedVerbatim(): void
    {
        self::assertSame('(max-width: 1023px) 75vw', (string)Size::mediaQuery('(max-width: 1023px)', '75vw'));
    }

    public function testAnEmptyMediaQueryThrows(): void
    {
        $this->expectException(InvalidConfigException::class);
        Size::mediaQuery('', '100vw');
    }

    public function testAnEmptyValueThrows(): void
    {
        $this->expectException(InvalidConfigException::class);
        Size::value('');
    }
}
