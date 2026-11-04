<?php

declare(strict_types=1);

namespace App\Services\Illuminate;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the Bi-Tech Illuminate challenge API.
 */
final class ChallengeApi
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $token,
    ) {}

    /**
     * @return array<string, mixed> Raw credentials payload (ssh + database).
     */
    public function credentials(): array
    {
        return $this->request()->get('/challenge/ssh-key')->throw()->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function submitRepo(string $repoUrl, string $cvPath): array
    {
        $response = $this->request()
            ->attach('cv', fopen($cvPath, 'r'), basename($cvPath), ['Content-Type' => 'application/pdf'])
            ->post('/challenge/submit-repo', ['repo_url' => $repoUrl]);

        return $response->throw()->json() ?? ['status' => $response->status()];
    }

    private function request(): PendingRequest
    {
        if (blank($this->token)) {
            throw new RuntimeException('BITECH_TOKEN is not set.');
        }

        return Http::baseUrl($this->baseUrl)
            ->withToken($this->token)
            ->acceptJson()
            ->timeout(20);
    }
}
