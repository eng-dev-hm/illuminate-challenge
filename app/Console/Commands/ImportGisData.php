<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Incident;
use App\Models\Neighborhood;
use App\Services\Illuminate\RemoteDatabase;
use App\Support\Geo;
use App\Support\PostgresGeometry;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class ImportGisData extends Command
{
    protected $signature = 'illuminate:import {--fresh : Empty the local tables first}';

    protected $description = 'Stage 3: copy neighborhoods and incidents from the remote PostgreSQL into SQLite';

    // Read the remote tables in pieces so a big table never has to fit in memory.
    private const CHUNK_SIZE = 500;

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
            ->chunk(self::CHUNK_SIZE, function ($rows) {
                foreach ($rows as $row) {
                    $boundary = PostgresGeometry::polygon($row->boundary);
                    [$lat, $lng] = Geo::centroid($boundary);

                    Neighborhood::updateOrCreate(['id' => $row->id], [
                        'name' => $row->name,
                        'boundary' => $boundary,
                        'centroid_lat' => $lat,
                        'centroid_lng' => $lng,
                        'properties' => json_decode($row->properties, true),
                    ]);
                }
            });
    }

    private function importIncidents(Connection $pg): void
    {
        // Arrays are converted to JSON by Postgres, so we can store them as they are.
        $pg->table('gis_data.incidents')
            ->selectRaw('
                id,
                location::text AS location,
                metadata::text AS metadata,
                array_to_json(tags)::text AS tags,
                array_to_json(reports)::text AS reports,
                occurred_at
            ')
            ->orderBy('id')
            ->chunk(self::CHUNK_SIZE, function ($rows) {
                foreach ($rows as $row) {
                    [$lat, $lng] = PostgresGeometry::point($row->location);
                    $metadata = json_decode($row->metadata, true);

                    Incident::updateOrCreate(['id' => $row->id], [
                        'lat' => $lat,
                        'lng' => $lng,
                        'code' => $metadata['incident']['code'] ?? null,
                        'metadata' => $metadata,
                        'tags' => json_decode($row->tags, true),
                        'reports' => json_decode($row->reports, true),
                        'occurred_at' => $row->occurred_at,
                    ]);
                }
            });
    }
}
