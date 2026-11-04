<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Incident;
use App\Models\Neighborhood;
use Illuminate\Console\Command;

class RevealDonutFlag extends Command
{
    protected $signature = 'illuminate:donut {neighborhood=NB-7A2F : Neighborhood name} {--details : Print every matching incident}';

    protected $description = 'Stage 3: concatenate the codes of incidents in the donut, nearest first';

    public function handle(): int
    {
        $neighborhood = Neighborhood::firstWhere('name', $this->argument('neighborhood'));

        if (! $neighborhood) {
            $this->components->error('Neighborhood not found. Did you run illuminate:import?');

            return self::FAILURE;
        }

        $incidents = $neighborhood->incidents;

        $this->components->twoColumnDetail('Centroid', sprintf('%.6f, %.6f', $neighborhood->centroid_lat, $neighborhood->centroid_lng));
        $this->components->twoColumnDetail('Incidents in ring', (string) $incidents->count());

        if ($this->option('details')) {
            $this->table(
                ['#', 'id', 'distance km', 'code'],
                $incidents->values()->map(fn (Incident $i, int $n) => [
                    $n + 1, $i->id, number_format($i->distance_km, 4), $i->code,
                ]),
            );
        }

        $this->newLine();
        $this->line($incidents->pluck('code')->implode(''));

        return self::SUCCESS;
    }
}
