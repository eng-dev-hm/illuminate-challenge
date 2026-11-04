<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Illuminate\RemoteDatabase;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;

class HuntRemoteFlag extends Command
{
    protected $signature = 'illuminate:hunt';

    protected $description = 'Stage 2: tunnel into the challenge PostgreSQL and search every column for the flag';

    public function handle(RemoteDatabase $remote): int
    {
        $this->components->info('Opening SSH tunnel...');

        $hits = $remote->run(function (Connection $db) {
            // Every column of every user table.
            $columns = $db->select("
                SELECT table_schema, table_name, column_name
                FROM information_schema.columns
                WHERE table_schema NOT IN ('pg_catalog', 'information_schema')
            ");

            $hits = [];

            foreach ($columns as $c) {
                $table = $db->getQueryGrammar()->wrapTable("{$c->table_schema}.{$c->table_name}");
                $column = $db->getQueryGrammar()->wrap($c->column_name);

                // ::text lets us search any type (text, jsonb, arrays...).
                $rows = $db->select(
                    "SELECT {$column}::text AS value FROM {$table} WHERE {$column}::text LIKE ?",
                    ['%ILLUMINATE{%'],
                );

                foreach ($rows as $row) {
                    $hits[] = ["{$c->table_schema}.{$c->table_name}", $c->column_name, $row->value];
                }
            }

            return $hits;
        });

        if ($hits === []) {
            $this->components->warn('No flag found.');

            return self::FAILURE;
        }

        $this->table(['Table', 'Column', 'Value'], $hits);

        return self::SUCCESS;
    }
}
