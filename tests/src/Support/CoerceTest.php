<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Telemetry\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Telemetry\Support\Coerce;
use WaffleTests\Commons\Telemetry\AbstractTestCase;

#[CoversClass(Coerce::class)]
final class CoerceTest extends AbstractTestCase
{
    public function testToFloat(): void
    {
        static::assertSame(1.5, Coerce::toFloat(1.5));
        static::assertSame(3.0, Coerce::toFloat(3));
        static::assertSame(0.0, Coerce::toFloat('nope'));
        static::assertSame(0.0, Coerce::toFloat(null));
    }

    public function testToString(): void
    {
        static::assertSame('hi', Coerce::toString('hi'));
        static::assertSame('', Coerce::toString(42));
        static::assertSame('', Coerce::toString(null));
    }

    public function testToArray(): void
    {
        static::assertSame(['a' => 1], Coerce::toArray(['a' => 1]));
        static::assertSame([], Coerce::toArray('nope'));
    }

    public function testToStringList(): void
    {
        static::assertSame(['a', 'b'], Coerce::toStringList(['a', 1, 'b', null]));
        static::assertSame([], Coerce::toStringList('nope'));
    }

    public function testToStringMap(): void
    {
        // Integer keys are dropped; a non-string value coerces to ''.
        static::assertSame(['k' => 'v', 'bad' => ''], Coerce::toStringMap(['k' => 'v', 2 => 'dropped', 'bad' => 123]));
        static::assertSame([], Coerce::toStringMap('nope'));
    }
}
