<?php

declare(strict_types=1);

namespace App\Relations;

use App\Models\Incident;
use App\Models\Neighborhood;
use App\Support\Geo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Incidents that are between $fromKm and $toKm from a neighborhood's center.
 *
 * There is no foreign key between the tables, so we extend the base Relation
 * class (the same parent HasOne / BelongsToMany use) and decide ourselves
 * which incidents belong to which neighborhood.
 *
 * 1. SQL keeps only incidents inside a square around the outer circle.
 * 2. PHP calculates the exact distance, keeps the ring, sorts nearest first.
 */
class Donut extends Relation
{
    public function __construct(
        Neighborhood $neighborhood,
        protected float $fromKm,
        protected float $toKm,
    ) {
        parent::__construct(Incident::query(), $neighborhood);
    }

    /** Used for $neighborhood->incidents (one neighborhood). */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->whereInsideBox($this->query, $this->parent);
        }
    }

    /** Used for Neighborhood::with('incidents') (many neighborhoods, one query). */
    public function addEagerConstraints(array $models): void
    {
        $this->query->where(function (Builder $query) use ($models) {
            foreach ($models as $neighborhood) {
                $query->orWhere(fn (Builder $q) => $this->whereInsideBox($q, $neighborhood));
            }
        });
    }

    public function initRelation(array $models, $relation): array
    {
        foreach ($models as $neighborhood) {
            $neighborhood->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /** Give each neighborhood only the incidents inside its own ring. */
    public function match(array $models, Collection $results, $relation): array
    {
        foreach ($models as $neighborhood) {
            $neighborhood->setRelation($relation, $this->ring($results, $neighborhood));
        }

        return $models;
    }

    public function getResults(): Collection
    {
        return $this->get();
    }

    /**
     * Relation::get() would return the whole square, so filter it here too.
     * Now $neighborhood->incidents and ->incidents()->get() give the same result.
     */
    public function get($columns = ['*']): Collection
    {
        return $this->ring($this->query->get($columns), $this->parent);
    }

    /** Eager loading: return all rows, match() does the per-neighborhood filtering. */
    public function getEager(): Collection
    {
        return $this->query->get();
    }

    protected function whereInsideBox(Builder $query, Neighborhood $neighborhood): void
    {
        [$minLat, $maxLat, $minLng, $maxLng] = Geo::box(
            $neighborhood->centroid_lat,
            $neighborhood->centroid_lng,
            $this->toKm,
        );

        $query->whereBetween('lat', [$minLat, $maxLat])
            ->whereBetween('lng', [$minLng, $maxLng]);
    }

    /** Keep incidents where from < distance <= to, nearest first. */
    protected function ring(Collection $incidents, Neighborhood $neighborhood): Collection
    {
        return $incidents
            ->map(function (Incident $incident) use ($neighborhood) {
                $incident = clone $incident; // a neighbor's ring may share this incident
                $incident->distance_km = Geo::distanceKm(
                    $neighborhood->centroid_lat,
                    $neighborhood->centroid_lng,
                    $incident->lat,
                    $incident->lng,
                );

                return $incident;
            })
            ->filter(fn (Incident $i) => $i->distance_km > $this->fromKm && $i->distance_km <= $this->toKm)
            ->sortBy('distance_km')
            ->values();
    }
}
