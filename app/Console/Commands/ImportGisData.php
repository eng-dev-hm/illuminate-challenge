<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Incident;
use App\Models\Neighborhood;
use App\Services\Illuminate\RemoteDatabase;
use App\Support\Geo;
use App\Support\PostgresGeometry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

#[Signature('illuminate:import {--fresh : Empty the local tables first}')]
#[Description('Stage 3: copy neighborhoods and incidents from the remote PostgreSQL into SQLite')]
class ImportGisData extends Command
{
    private const CHUNK = 500;

    public function handle(RemoteDatabase $remote): int
    {
        $remote->run(function (Connection $pg) {
            DB::transaction(function () use ($pg) {
                if ($this->option('fresh')) {
                    Incident::query()->delete();
                    Neighborhood::query()->delete();
                }

                $this->importNeighborhoods($pg);
                $this->importIncidents($pg);
            });
        });

        $this->components->info(sprintf(
            'Imported %d neighborhoods and %d incidents.',
            Neighborhood::count(),
            Incident::count(),
        ));

        return self::SUCCESS;
    }

    private function importNeighborhoods(Connection $pg): void
    {
        $pg->table('gis_data.neighborhoods')
            ->selectRaw('id, name, boundary::text AS boundary, properties::text AS properties')
            ->orderBy('id')
            ->chunk(self::CHUNK, function ($rows) {
                $records = $rows->map(function ($row) {
                    $boundary = PostgresGeometry::polygon($row->boundary);
                    [$lat, $lng] = Geo::centroid($boundary);

                    return [
                        'id' => $row->id,
                        'name' => $row->name,
                        'boundary' => json_encode($boundary),
                        'centroid_lat' => $lat,
                        'centroid_lng' => $lng,
                        'properties' => $row->properties,
                    ];
                });

                Neighborhood::upsert($records->all(), ['id']);
            });
    }

    private function importIncidents(Connection $pg): void
    {
        $pg->table('gis_data.incidents')
            ->selectRaw(<<<'SQL'
                id,
                location::text AS location,
                metadata::text AS metadata,
                array_to_json(tags)::text AS tags,
                array_to_json(reports)::text AS reports,
                occurred_at
            SQL)
            ->orderBy('id')
            ->chunk(self::CHUNK, function ($rows) {
                $records = $rows->map(function ($row) {
                    [$lat, $lng] = PostgresGeometry::point($row->location);
                    $metadata = json_decode($row->metadata, true, flags: JSON_THROW_ON_ERROR);

                    return [
                        'id' => $row->id,
                        'lat' => $lat,
                        'lng' => $lng,
                        'code' => data_get($metadata, 'incident.code'),
                        'metadata' => $row->metadata,
                        'tags' => $row->tags,
                        'reports' => $row->reports,
                        'occurred_at' => $row->occurred_at,
                    ];
                });

                Incident::upsert($records->all(), ['id']);
            });
    }
}
