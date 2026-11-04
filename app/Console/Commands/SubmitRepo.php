<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Illuminate\ChallengeApi;
use Illuminate\Console\Command;

class SubmitRepo extends Command
{
    protected $signature = 'illuminate:submit-repo {repo_url : Public GitHub repository URL} {cv : Path to your CV (PDF)}';

    protected $description = 'Final step: send the repository URL and CV to Bi-Tech';

    public function handle(ChallengeApi $api): int
    {
        $repo = $this->argument('repo_url');
        $cv = $this->argument('cv');

        if (! str_ends_with(strtolower($cv), '.pdf') || ! is_file($cv)) {
            $this->components->error("CV must be an existing PDF file: {$cv}");

            return self::FAILURE;
        }

        if (! $this->confirm("Submit {$repo} with ".basename($cv).'?')) {
            return self::FAILURE;
        }

        $response = $api->submitRepo($repo, $cv);

        $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
