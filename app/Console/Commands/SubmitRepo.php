<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Illuminate\ChallengeApi;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('illuminate:submit-repo {repo_url : Public GitHub repository URL} {cv : Path to your CV (PDF)}')]
#[Description('Final step: send the repository URL and CV to Bi-Tech')]
class SubmitRepo extends Command
{
    public function handle(ChallengeApi $api): int
    {
        $repo = (string) $this->argument('repo_url');
        $cv = (string) $this->argument('cv');

        $validator = Validator::make(compact('repo', 'cv'), [
            'repo' => ['required', 'url', 'regex:#^https://github\.com/[\w.-]+/[\w.-]+/?$#'],
        ]);

        if ($validator->fails()) {
            $this->components->error('repo_url must look like https://github.com/<user>/<repo>');

            return self::FAILURE;
        }

        if (! is_file($cv) || mime_content_type($cv) !== 'application/pdf') {
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
