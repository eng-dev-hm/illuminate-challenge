<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Postgres sends geometric types as text. In this database they are (lat, lng).
 */
class PostgresGeometry
{
    /**
     * "(33.33,44.35)" => [33.33, 44.35]
     *
     * @return array{0: float, 1: float}
     */
    public static function point(string $value): array
    {
        [$lat, $lng] = explode(',', trim($value, '() '));

        return [(float) $lat, (float) $lng];
    }

    /**
     * "((33.32,44.34),(33.34,44.365),...)" => [[33.32, 44.34], [33.34, 44.365], ...]
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public static function polygon(string $value): array
    {
        $points = explode('),(', trim($value, '() '));

        return array_map(fn (string $point) => self::point($point), $points);
    }
}
