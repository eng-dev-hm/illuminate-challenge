<?php

declare(strict_types=1);

namespace App\Services\Illuminate;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Opens an SSH local port-forward to the challenge server and exposes the
 * PostgreSQL instance behind it as a regular Laravel database connection.
 *
 *   127.0.0.1:{tunnel_port} ──ssh──> illuminate.bitech.com.sa ──> localhost:5432
 *
 * Commands use it through run(), which guarantees the tunnel process and
 * the temporary key file are torn down even when the callback throws.
 */
final class RemoteDatabase
{
    public const CONNECTION = 'illuminate_remote';

    public function __construct(
        private readonly ChallengeApi $api,
        private readonly int $localPort,
    ) {}

    /**
     * @template T
     *
     * @param  Closure(Connection): T  $callback
     * @return T
     */
    public function run(Closure $callback): mixed
    {
        ['ssh' => $ssh, 'database' => $db] = $this->api->credentials();

        $keyFile = $this->writeKey($ssh['private_key']);
        $tunnel = null;

        try {
            $tunnel = $this->openTunnel($ssh, $db, $keyFile);

            config(['database.connections.'.self::CONNECTION => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'port' => $this->localPort,
                'database' => $db['name'],
                'username' => $db['username'],
                'password' => $db['password'],
                'charset' => 'utf8',
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ]]);

            return $callback(DB::connection(self::CONNECTION));
        } finally {
            DB::purge(self::CONNECTION);
            $tunnel?->stop(3);
            @unlink($keyFile);
        }
    }

    private function writeKey(string $key): string
    {
        $path = tempnam(sys_get_temp_dir(), 'illuminate_key_');
        chmod($path, 0600);
        file_put_contents($path, rtrim($key)."\n");

        return $path;
    }

    /**
     * @param  array<string, mixed>  $ssh
     * @param  array<string, mixed>  $db
     */
    private function openTunnel(array $ssh, array $db, string $keyFile): Process
    {
        $process = new Process([
            'ssh', '-N',
            '-i', $keyFile,
            '-p', (string) $ssh['port'],
            '-o', 'BatchMode=yes',
            '-o', 'IdentitiesOnly=yes',
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'ExitOnForwardFailure=yes',
            '-o', 'ServerAliveInterval=15',
            '-L', sprintf('127.0.0.1:%d:%s:%d', $this->localPort, $db['host'], $db['port']),
            sprintf('%s@%s', $ssh['username'], $ssh['host']),
        ]);
        $process->setTimeout(null);
        $process->start();

        // Wait until the forwarded port accepts connections (max ~10s).
        for ($i = 0; $i < 50; $i++) {
            if (! $process->isRunning()) {
                throw new RuntimeException('SSH tunnel failed: '.trim($process->getErrorOutput()));
            }
            if ($socket = @fsockopen('127.0.0.1', $this->localPort, $errno, $errstr, 0.2)) {
                fclose($socket);

                return $process;
            }
            usleep(200_000);
        }

        $process->stop(1);

        throw new RuntimeException('SSH tunnel did not become ready in time.');
    }
}
