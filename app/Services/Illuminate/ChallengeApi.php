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
