<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Parses the text form of PostgreSQL geometric types.
 * The challenge data stores them as (lat, lng).
 */
final class PostgresGeometry
{
    private const NUMBER = '[-+]?\d*\.?\d+(?:[eE][-+]?\d+)?';

    /** @return array{0: float, 1: float} */
    public static function point(string $value): array
    {
        if (! preg_match('/^\s*\(\s*('.self::NUMBER.')\s*,\s*('.self::NUMBER.')\s*\)\s*$/', $value, $m)) {
            throw new InvalidArgumentException("Invalid point: {$value}");
        }

        return [(float) $m[1], (float) $m[2]];
    }

    /** @return list<array{0: float, 1: float}> */
    public static function polygon(string $value): array
    {
        preg_match_all('/\(\s*('.self::NUMBER.')\s*,\s*('.self::NUMBER.')\s*\)/', $value, $m, PREG_SET_ORDER);

        if (count($m) < 3) {
            throw new InvalidArgumentException("Invalid polygon: {$value}");
        }

        return array_map(fn (array $p) => [(float) $p[1], (float) $p[2]], $m);
    }
}
