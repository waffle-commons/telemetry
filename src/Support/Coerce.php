<?php

declare(strict_types=1);

namespace Waffle\Commons\Telemetry\Support;

use function array_filter;
use function array_keys;
use function array_values;
use function is_array;
use function is_float;
use function is_int;
use function is_string;

/**
 * Tiny type-coercion helpers for the inherently-`mixed` returns of APCu and
 * `json_decode()`. Each takes a `mixed` parameter and narrows it with `is_*()`
 * checks (or `array_filter` with a typed predicate), so callers never assign or
 * cast `mixed` directly — keeping the analyzer clean WITHOUT suppressions, per the
 * zero-baseline mandate.
 */
final class Coerce
{
    public static function toFloat(mixed $value): float
    {
        return is_int($value) || is_float($value) ? (float) $value : 0.0;
    }

    public static function toString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /** @return array<array-key, mixed> */
    public static function toArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /** @return list<string> */
    public static function toStringList(mixed $value): array
    {
        return array_values(array_filter(self::toArray($value), is_string(...)));
    }

    /** @return array<string, string> */
    public static function toStringMap(mixed $value): array
    {
        $array = self::toArray($value);
        $map = [];
        // Iterate only the string keys (a list<string>, never a mixed array); each
        // value is coerced to string — so the result is inferred as array<string, string>.
        foreach (self::toStringList(array_keys($array)) as $key) {
            $map[$key] = self::toString($array[$key] ?? null);
        }

        return $map;
    }
}
