<?php

declare(strict_types=1);

namespace App\Relations;

use App\Models\Incident;
use App\Models\Neighborhood;
use App\Support\Geo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use LogicException;

/**
 * Neighborhood -> incidents lying in a ring (annulus) around its centroid.
 *
 * There is no foreign key between the two tables, the link is purely
 * geometric, so none of the built-in relations fit.
 *
 * Two passes:
 *  1. SQL: a lat/lng bounding box around the outer circle. Cheap, uses the
 *     (lat, lng) index and throws away almost everything.
 *  2. PHP: exact haversine distance, keep inner <= d <= outer, sort by d.
 *
 * SQLite has no trig functions by default, which is why step 2 is not SQL.
 *
 * @extends Relation<Incident, Neighborhood, Collection<int, Incident>>
 */
class Donut extends Relation
{
    public function __construct(
        Builder $query,
        Neighborhood $parent,
        protected readonly float $innerKm,
        protected readonly float $outerKm,
    ) {
        if ($innerKm < 0 || $outerKm <= $innerKm) {
            throw new InvalidArgumentException('Expected 0 <= inner < outer.');
        }

        parent::__construct($query, $parent);
    }

    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->whereInBox($this->query, $this->parent);
        }
    }

    /** @param  array<int, Neighborhood>  $models */
    public function addEagerConstraints(array $models): void
    {
        $this->query->where(function (Builder $q) use ($models) {
            foreach ($models as $model) {
                $q->orWhere(fn (Builder $box) => $this->whereInBox($box, $model));
            }
        });
    }

    /** @param  array<int, Neighborhood>  $models */
    public function initRelation(array $models, $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * The eager query returns the union of every parent's box; each parent
     * keeps only the incidents that are really inside its own ring.
     *
     * @param  array<int, Neighborhood>  $models
     */
    public function match(array $models, Collection $results, $relation): array
    {
        foreach ($models as $model) {
            // Clone so distance_km is per neighborhood when rings overlap.
            $own = $results->map(fn (Incident $incident) => clone $incident);

            $model->setRelation($relation, $this->ring($own, $model));
        }

        return $models;
    }

    public function getResults(): Collection
    {
        return $this->get();
    }

    /**
     * Relation::get() goes straight to the builder, which would return the
     * whole bounding box. Apply the ring filter here too, so that
     * $neighborhood->incidents()->get() and ->incidents behave the same.
     */
    public function get($columns = ['*']): Collection
    {
        return $this->ring($this->query->get($columns), $this->parent);
    }

    /** Eager loading must get the raw box rows; match() does the filtering. */
    public function getEager(): Collection
    {
        return $this->query->get();
    }

    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*']): Builder
    {
        throw new LogicException('has()/whereHas() are not supported on a distance relation.');
    }

    protected function whereInBox(Builder $query, Neighborhood $neighborhood): void
    {
        $box = Geo::boundingBox($neighborhood->centroid_lat, $neighborhood->centroid_lng, $this->outerKm);

        $query->whereBetween('lat', [$box['minLat'], $box['maxLat']])
            ->whereBetween('lng', [$box['minLng'], $box['maxLng']]);
    }

    /**
     * @param  Collection<int, Incident>  $incidents
     * @return Collection<int, Incident>
     */
    protected function ring(Collection $incidents, Neighborhood $neighborhood): Collection
    {
        return $incidents
            ->each(fn (Incident $i) => $i->setAttribute('distance_km', Geo::haversine(
                $neighborhood->centroid_lat,
                $neighborhood->centroid_lng,
                $i->lat,
                $i->lng,
            )))
            ->filter(fn (Incident $i) => $i->distance_km >= $this->innerKm && $i->distance_km <= $this->outerKm)
            ->sortBy('distance_km')
            ->values();
    }
}
