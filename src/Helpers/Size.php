<?php

declare(strict_types=1);

namespace Hirtz\Media\Helpers;

use Hirtz\Media\Module;
use Hirtz\Media\Modules\ModuleTrait;
use Stringable;
use yii\base\InvalidConfigException;

/**
 * One `<media-condition> <length>` pair of an image's `sizes` attribute, or the bare `<length>` that must end it.
 *
 * @see https://html.spec.whatwg.org/multipage/images.html#sizes-attributes
 */
final readonly class Size implements Stringable
{
    use ModuleTrait;

    private function __construct(
        public ?string $condition,
        public string $value,
    ) {
        if ($value === '') {
            throw new InvalidConfigException('A size needs a value.');
        }

        foreach ([$condition, $value] as $part) {
            if ($part !== null && !self::hasBalancedParentheses($part)) {
                throw new InvalidConfigException("The size \"$this\" has unbalanced parentheses.");
            }
        }

        // `100vw-40px` is one dimension with the unit `vw-40px` to CSS, so `calc()` needs the operator spaced.
        if (preg_match('/(?:\d[a-z%]*|\))(?:(?<operator>[+-])|\s+(?<operator2>[+-])\S)/i', $value, $matches)) {
            $operator = $matches['operator'] ?: ($matches['operator2'] ?? '');
            throw new InvalidConfigException("The size \"$this\" needs whitespace around the \"$operator\" operator, or a browser drops it.");
        }
    }

    private static function hasBalancedParentheses(string $text): bool
    {
        $depth = 0;

        foreach (str_split($text) as $char) {
            $depth += match ($char) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            if ($depth < 0) {
                return false;
            }
        }

        return $depth === 0;
    }

    /**
     * @param string $name a breakpoint of {@see Module::$breakpoints}, validated here so a renamed one is caught at
     * declaration time rather than dropped from the rendered attribute
     */
    public static function breakpoint(string $name, string $value): self
    {
        $breakpoint = static::getModule()->breakpoints[$name] ?? null;

        if ($breakpoint === null) {
            throw new InvalidConfigException("Breakpoint \"$name\" is not declared by the media module.");
        }

        $maxWidth = is_int($breakpoint) ? $breakpoint - 1 : null;

        return new self($maxWidth === null ? (string)$breakpoint : "(max-width:{$maxWidth}px)", $value);
    }

    public static function mediaQuery(string $query, string $value): self
    {
        if ($query === '') {
            throw new InvalidConfigException('A size needs a media query.');
        }

        return new self($query, $value);
    }

    public static function value(string $value): self
    {
        return new self(null, $value);
    }

    public function isConditional(): bool
    {
        return $this->condition !== null;
    }

    public function __toString(): string
    {
        return $this->condition === null ? $this->value : "$this->condition $this->value";
    }
}
