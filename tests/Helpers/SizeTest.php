<?php

declare(strict_types=1);

namespace Hirtz\Media\Tests\Helpers;

use Hirtz\Media\Helpers\Size;
use Hirtz\Media\Modules\ModuleTrait;
use Hirtz\Media\Test\TestCase;
use PHPUnit\Framework\Attributes\TestWith;
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

    /**
     * Each of these is what a browser parses, so none may throw.
     */
    #[TestWith(['calc(100vw - 40px)'])]
    #[TestWith(['min(408px, calc(100vw - 40px))'])]
    #[TestWith(['min(408px,calc(100vw - 40px))'])]
    #[TestWith(['calc(-1 * 10px + 100%)'])]
    #[TestWith(['calc(100vw - var(--gutter-width))'])]
    #[TestWith(['clamp(20rem, 50vw, 60rem)'])]
    public function testAWellFormedValueIsAccepted(string $value): void
    {
        self::assertSame($value, (string)Size::value($value));
    }

    #[TestWith(['min(1400px,calc(100vw - 100px)'])]
    #[TestWith(['calc(100vw - 40px))'])]
    #[TestWith([')100vw('])]
    public function testUnbalancedParenthesesThrow(string $value): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('unbalanced parentheses');
        Size::value($value);
    }

    /**
     * CSS reads `100vw-40px` as one dimension with the unit `vw-40px`, so the whole size is dropped.
     */
    #[TestWith(['calc(100vw-40px)', '-'])]
    #[TestWith(['min(408px,calc(100vw-40px))', '-'])]
    #[TestWith(['calc(100vw+40px)', '+'])]
    #[TestWith(['calc(100vw -40px)', '-'])]
    #[TestWith(['calc((100vw - 2rem)-40px)', '-'])]
    public function testAnUnspacedOperatorThrows(string $value, string $operator): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage("the \"$operator\" operator");
        Size::value($value);
    }

    public function testAnUnbalancedMediaQueryThrows(): void
    {
        $this->expectException(InvalidConfigException::class);
        Size::mediaQuery('(min-width:48rem', '100vw');
    }
}
