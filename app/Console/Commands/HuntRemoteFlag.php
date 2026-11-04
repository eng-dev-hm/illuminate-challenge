<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Illuminate\RemoteDatabase;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;

#[Signature('illuminate:hunt {--schema-only : Only print the schema, do not search}')]
#[Description('Stage 2: tunnel into the challenge PostgreSQL and search every table/column for the flag')]
class HuntRemoteFlag extends Command
{
    private const FLAG_PATTERN = 'ILLUMINATE{%';

    public function handle(RemoteDatabase $remote): int
    {
        $this->components->info('Opening SSH tunnel...');

        $hits = $remote->run(function (Connection $db): array {
            $columns = $db->select(<<<'SQL'
                SELECT c.table_schema, c.table_name, c.column_name, c.data_type
                FROM information_schema.columns c
                JOIN information_schema.tables t
                  ON t.table_schema = c.table_schema AND t.table_name = c.table_name
                WHERE c.table_schema NOT IN ('pg_catalog', 'information_schema')
                ORDER BY c.table_schema, c.table_name, c.ordinal_position
            SQL);

            foreach (collect($columns)->groupBy(fn ($c) => "{$c->table_schema}.{$c->table_name}") as $table => $cols) {
                $this->line("<fg=yellow>{$table}</>: ".$cols->map(fn ($c) => "{$c->column_name} ({$c->data_type})")->implode(', '));
            }

            if ($this->option('schema-only')) {
                return [];
            }

            // Cast every column to text so the flag is found whatever the type
            // (text, json/jsonb, bytea comments, arrays...). Identifiers are
            // quoted by the grammar to stay safe with unusual names.
            $hits = [];
            foreach ($columns as $c) {
                $table = $db->getQueryGrammar()->wrapTable("{$c->table_schema}.{$c->table_name}");
                $column = $db->getQueryGrammar()->wrap($c->column_name);

                $rows = $db->select(
                    "SELECT {$column}::text AS value FROM {$table} WHERE {$column}::text LIKE ? LIMIT 5",
                    ['%'.self::FLAG_PATTERN],
                );

                foreach ($rows as $row) {
                    $hits[] = ["{$c->table_schema}.{$c->table_name}", $c->column_name, $row->value];
                }
            }

            return $hits;
        });

        if ($this->option('schema-only')) {
            return self::SUCCESS;
        }

        if ($hits === []) {
            $this->components->warn('No ILLUMINATE{...} value found.');

            return self::FAILURE;
        }

        $this->table(['Table', 'Column', 'Value'], $hits);

        return self::SUCCESS;
    }
}
