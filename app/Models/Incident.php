<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property float $lat
 * @property float $lng
 * @property string|null $code
 * @property array<string, mixed> $metadata
 * @property list<string>|null $tags
 * @property list<mixed>|null $reports
 * @property \Illuminate\Support\Carbon|null $occurred_at
 * @property-read float|null $distance_km Set by the Donut relation.
 */
class Incident extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'metadata' => 'array',
            'tags' => 'array',
            'reports' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
