<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportDjangoData extends Command
{
    protected $signature = 'board:import-django';

    protected $description = 'Copy users, posts and comments out of the djbb-lite tables that share this database';

    private const LEGACY_TABLES = ['auth_user', 'board_post', 'board_comment'];

    /**
     * Django primary keys are carried over so author_id and post_id keep pointing at the
     * same rows; sequences are re-synced afterwards.
     */
    private const MAPS = [
        'users' => [
            'table' => 'auth_user',
            'columns' => ['id', 'username', 'email', 'password', 'is_staff', 'date_joined' => 'created_at'],
        ],
        'posts' => [
            'table' => 'board_post',
            'columns' => ['id', 'title', 'body', 'author_id', 'created_at'],
        ],
        'comments' => [
            'table' => 'board_comment',
            'columns' => ['id', 'body', 'post_id', 'author_id', 'created_at'],
        ],
    ];

    public function handle(): int
    {
        if ($missing = array_values(array_filter(self::LEGACY_TABLES, fn ($table) => ! DB::getSchemaBuilder()->hasTable($table)))) {
            $this->components->error('Missing Django tables: '.implode(', ', $missing).'.');
            $this->components->info('Point DATABASE_URL at the branch where djbb-lite has run its migrations.');

            return self::FAILURE;
        }

        foreach (self::MAPS as $destination => $source) {
            $count = $this->copyRows($source['table'], $destination, $source['columns']);
            $this->syncSequence($destination);
            $this->components->info(str_pad($destination.':', 12)." {$count} rows copied");
        }

        $this->components->warn('Django pbkdf2 hashes were copied verbatim; each account converts to bcrypt on its next login.');

        return self::SUCCESS;
    }

    /**
     * Rows already present are skipped, so re-running never clobbers edits made in Laravel.
     *
     * @param  array<int, string>|array<string, string>  $columns  list, or source => destination
     */
    private function copyRows(string $from, string $to, array $columns): int
    {
        $names = [];

        foreach ($columns as $key => $value) {
            $names[is_int($key) ? $value : $key] = $value;
        }

        $existing = DB::table($to)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $rows = DB::table($from)
            ->select(array_keys($names))
            ->orderBy('id')
            ->get()
            ->reject(fn (object $row) => in_array((int) $row->id, $existing, true))
            ->map(fn (object $row) => array_combine(
                array_values($names),
                array_map(fn ($source) => $row->{$source} ?? null, array_keys($names))
            ))
            ->values()
            ->all();

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table($to)->insert($chunk);
        }

        return count($rows);
    }

    private function syncSequence(string $table): void
    {
        $max = DB::table($table)->max('id');

        DB::statement("select setval(pg_get_serial_sequence('{$table}', 'id'), ?, ?)", [$max ?? 1, $max !== null]);
    }
}
