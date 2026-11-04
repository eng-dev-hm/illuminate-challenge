<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class Geo
{
    public const EARTH_RADIUS_KM = 6371.0;

    /**
     * Great-circle distance between two points, in km.
     */
    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }

    /**
     * Area-weighted centroid of a simple polygon (shoelace formula).
     *
     * Averaging the vertices only works for regular shapes; NB-7A2F is a
     * trapezoid, so the vertex mean is pulled towards the short side.
     *
     * @param  list<array{0: float, 1: float}>  $vertices  [lat, lng] pairs
     * @return array{0: float, 1: float}
     */
    public static function centroid(array $vertices): array
    {
        $n = count($vertices);

        if ($n < 3) {
            throw new InvalidArgumentException('A polygon needs at least 3 vertices.');
        }

        $area = $cx = $cy = 0.0;

        for ($i = 0; $i < $n; $i++) {
            [$x0, $y0] = $vertices[$i];
            [$x1, $y1] = $vertices[($i + 1) % $n];

            $cross = $x0 * $y1 - $x1 * $y0;
            $area += $cross;
            $cx += ($x0 + $x1) * $cross;
            $cy += ($y0 + $y1) * $cross;
        }

        if (abs($area) < 1e-12) {
            throw new InvalidArgumentException('Degenerate polygon (zero area).');
        }

        $area /= 2;

        return [$cx / (6 * $area), $cy / (6 * $area)];
    }

    /**
     * Rough lat/lng box that fully contains a circle of $km around a point.
     * Used to let the database discard far-away rows before the exact check.
     *
     * @return array{minLat: float, maxLat: float, minLng: float, maxLng: float}
     */
    public static function boundingBox(float $lat, float $lng, float $km): array
    {
        $dLat = rad2deg($km / self::EARTH_RADIUS_KM);
        $dLng = rad2deg($km / (self::EARTH_RADIUS_KM * cos(deg2rad($lat))));

        return [
            'minLat' => $lat - $dLat,
            'maxLat' => $lat + $dLat,
            'minLng' => $lng - $dLng,
            'maxLng' => $lng + $dLng,
        ];
    }
}
