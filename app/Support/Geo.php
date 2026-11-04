<?php

declare(strict_types=1);

namespace App\Support;

class Geo
{
    // 1 degree of latitude is about 111 km.
    public const KM_PER_DEGREE = 111.0;

    /**
     * Distance in km between two points.
     *
     * Neighborhoods are only a few km wide, so we can treat the area as flat:
     * convert the lat/lng difference to km and use Pythagoras.
     * Longitude degrees get shorter away from the equator, hence the cos().
     */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dy = ($lat2 - $lat1) * self::KM_PER_DEGREE;
        $dx = ($lng2 - $lng1) * self::KM_PER_DEGREE * cos(deg2rad($lat1));

        return sqrt($dx ** 2 + $dy ** 2);
    }

    /**
     * Center of the polygon = middle of its bounding box.
     *
     * @param  array<int, array{0: float, 1: float}>  $points  [lat, lng] pairs
     * @return array{0: float, 1: float}
     */
    public static function centroid(array $points): array
    {
        $lats = array_column($points, 0);
        $lngs = array_column($points, 1);

        return [
            (min($lats) + max($lats)) / 2,
            (min($lngs) + max($lngs)) / 2,
        ];
    }

    /**
     * A square around a point that contains the whole circle of $km.
     * Lets SQL throw away far rows before we calculate exact distances.
     *
     * @return array{0: float, 1: float, 2: float, 3: float} [minLat, maxLat, minLng, maxLng]
     */
    public static function box(float $lat, float $lng, float $km): array
    {
        $dLat = $km / self::KM_PER_DEGREE;
        $dLng = $km / (self::KM_PER_DEGREE * cos(deg2rad($lat)));

        return [$lat - $dLat, $lat + $dLat, $lng - $dLng, $lng + $dLng];
    }
}
