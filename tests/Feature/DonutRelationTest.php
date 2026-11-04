<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Neighborhood;
use App\Support\Geo;
use App\Support\PostgresGeometry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonutRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_parses_postgres_point_and_polygon(): void
    {
        $this->assertSame([33.33, 44.35], PostgresGeometry::point('(33.33,44.35)'));
        $this->assertSame(
            [[33.32, 44.34], [33.34, 44.365]],
            array_slice(PostgresGeometry::polygon('((33.32,44.34),(33.34,44.365),(33.3,44.3))'), 0, 2),
        );
    }

    public function test_centroid_is_the_middle_of_the_bounding_box(): void
    {
        $points = [[33.32, 44.34], [33.325, 44.365], [33.34, 44.365], [33.34, 44.34]];

        $this->assertEqualsWithDelta([33.33, 44.3525], Geo::centroid($points), 1e-9);
    }

    public function test_it_returns_only_incidents_inside_the_ring_ordered_by_distance(): void
    {
        $hood = $this->neighborhood('NB-TEST', 33.33, 44.35);

        $this->incidentAt(1, $hood, 0.2, 'X');  // inside the hole
        $this->incidentAt(2, $hood, 1.5, 'B');
        $this->incidentAt(3, $hood, 0.8, 'A');
        $this->incidentAt(4, $hood, 3.0, 'Z');  // outside the ring

        $this->assertSame('AB', $hood->incidents->pluck('code')->implode(''));
        $this->assertSame('AB', $hood->incidents()->get()->pluck('code')->implode(''));
    }

    public function test_eager_loading_matches_each_neighborhood_separately(): void
    {
        $near = $this->neighborhood('NB-NEAR', 33.33, 44.35);
        $far = $this->neighborhood('NB-FAR', 10.0, 10.0);

        $this->incidentAt(1, $near, 1.0, 'A');
        $this->incidentAt(2, $far, 1.0, 'B');

        $hoods = Neighborhood::with('incidents')->orderBy('name')->get()->keyBy('name');

        $this->assertSame(['A'], $hoods['NB-NEAR']->incidents->pluck('code')->all());
        $this->assertSame(['B'], $hoods['NB-FAR']->incidents->pluck('code')->all());
    }

    private function neighborhood(string $name, float $lat, float $lng): Neighborhood
    {
        return Neighborhood::create([
            'id' => crc32($name) % 100000,
            'name' => $name,
            'boundary' => [],
            'centroid_lat' => $lat,
            'centroid_lng' => $lng,
        ]);
    }

    /** Put an incident $km north of the neighborhood center. */
    private function incidentAt(int $id, Neighborhood $hood, float $km, string $code): void
    {
        Incident::create([
            'id' => $id,
            'lat' => $hood->centroid_lat + $km / Geo::KM_PER_DEGREE,
            'lng' => $hood->centroid_lng,
            'code' => $code,
            'metadata' => [],
        ]);
    }
}
