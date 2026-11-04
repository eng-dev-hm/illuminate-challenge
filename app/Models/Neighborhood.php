<?php

declare(strict_types=1);

namespace App\Models;

use App\Relations\Donut;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property list<array{0: float, 1: float}> $boundary
 * @property float $centroid_lat
 * @property float $centroid_lng
 * @property array<string, mixed>|null $properties
 */
class Neighborhood extends Model
{
    public const DONUT_INNER_KM = 0.5;

    public const DONUT_OUTER_KM = 2.0;

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'boundary' => 'array',
            'properties' => 'array',
            'centroid_lat' => 'float',
            'centroid_lng' => 'float',
        ];
    }

    /**
     * Incidents inside the ring between 0.5 km and 2 km from the centroid,
     * nearest first.
     */
    public function incidents(): Donut
    {
        return new Donut(
            Incident::query(),
            $this,
            innerKm: self::DONUT_INNER_KM,
            outerKm: self::DONUT_OUTER_KM,
        );
    }
}
