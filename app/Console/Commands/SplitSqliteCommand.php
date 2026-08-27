<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class SplitSqliteCommand extends Command
{
    protected $signature = 'db:split-sqlite
        {--source= : Pad naar de oude gecombineerde sqlite (default: database/database.sqlite)}
        {--force : Overschrijf bestaande catalog/ledger}';

    protected $description = 'Splits de oude sqlite in catalog.sqlite (git) en ledger.sqlite (lokaal).';

    /** @var list<string> */
    private array $catalogTables = [
        'categories',
        'budgets',
        'import_rules',
        'savings_goals',
        'budget_month_values',
    ];

    /** @var list<string> */
    private array $ledgerTables = [
        'users',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'transactions',
        'bank_accounts',
        'enable_banking_sessions',
        'dynamic_budgets',
        'plaid_connections',
    ];

    public function handle(): int
    {
        $source = $this->option('source') ?: database_path('database.sqlite');
        if (! File::exists($source)) {
            $this->error("Bronbestand niet gevonden: {$source}");

            return self::FAILURE;
        }

        $catalogPath = database_path('catalog.sqlite');
        $ledgerPath = database_path('ledger.sqlite');

        if (! $this->option('force') && (File::exists($catalogPath) || File::exists($ledgerPath))) {
            $this->error('catalog.sqlite of ledger.sqlite bestaat al. Gebruik --force om te overschrijven.');

            return self::FAILURE;
        }

        foreach ([$catalogPath, $ledgerPath] as $path) {
            File::delete($path);
            File::delete($path.'-wal');
            File::delete($path.'-shm');
            File::delete($path.'-journal');
            File::put($path, '');
        }

        config([
            'database.connections.catalog.database' => $catalogPath,
            'database.connections.sqlite.database' => $ledgerPath,
            'database.connections.legacy_source' => [
                'driver' => 'sqlite',
                'database' => $source,
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('catalog');
        DB::purge('sqlite');

        $this->migrateTargets();
        $this->copyTables('catalog', $this->catalogTables);
        $this->copyTables('sqlite', $this->ledgerTables);
        $this->checkpoint('catalog');
        $this->checkpoint('sqlite');
        $this->pointEnvToLedger();

        $this->info('Klaar.');
        $this->line("Catalog (git): {$catalogPath}");
        $this->line("Ledger (niet git): {$ledgerPath}");
        $this->line('Zet DB_DATABASE in .env op het ledger-bestand als die nog naar database.sqlite wijst.');

        return self::SUCCESS;
    }

    private function migrateTargets(): void
    {
        Artisan::call('migrate', [
            '--database' => 'catalog',
            '--path' => 'database/migrations/catalog',
            '--force' => true,
        ]);
        $this->output->write(Artisan::output());

        Artisan::call('migrate', [
            '--database' => 'sqlite',
            '--path' => 'database/migrations',
            '--force' => true,
        ]);
        $this->output->write(Artisan::output());
    }

    /**
     * @param  list<string>  $tables
     */
    private function copyTables(string $targetConnection, array $tables): void
    {
        $source = DB::connection('legacy_source');
        $target = DB::connection($targetConnection);

        $target->statement('PRAGMA foreign_keys = OFF');

        foreach ($tables as $table) {
            if (! $this->sourceHasTable($table)) {
                $this->warn("Overgeslagen (niet in bron): {$table}");

                continue;
            }

            if (! Schema::connection($targetConnection)->hasTable($table)) {
                $this->warn("Overgeslagen (niet in doel): {$table}");

                continue;
            }

            $targetColumns = Schema::connection($targetConnection)->getColumnListing($table);
            $sourceColumns = $source->getSchemaBuilder()->getColumnListing($table);
            $columns = array_values(array_intersect($targetColumns, $sourceColumns));
            if ($columns === []) {
                continue;
            }

            $target->table($table)->delete();
            $rows = $source->table($table)->get();
            $copied = 0;
            foreach ($rows as $row) {
                $payload = [];
                foreach ($columns as $column) {
                    $payload[$column] = $row->{$column} ?? null;
                }
                $target->table($table)->insert($payload);
                $copied++;
            }

            $this->line("{$targetConnection}.{$table}: {$copied} rijen");
        }

        $this->copySequences($targetConnection, $tables);

        $target->statement('PRAGMA foreign_keys = ON');
    }

    /**
     * @param  list<string>  $tables
     */
    private function copySequences(string $targetConnection, array $tables): void
    {
        try {
            $sequences = DB::connection('legacy_source')->select('SELECT name, seq FROM sqlite_sequence');
        } catch (\Throwable) {
            return;
        }

        $target = DB::connection($targetConnection);
        foreach ($sequences as $sequence) {
            $name = $sequence->name ?? null;
            if (! is_string($name) || ! in_array($name, $tables, true)) {
                continue;
            }

            try {
                $target->table('sqlite_sequence')->updateOrInsert(
                    ['name' => $name],
                    ['seq' => $sequence->seq],
                );
            } catch (\Throwable) {
                continue;
            }
        }
    }

    private function sourceHasTable(string $table): bool
    {
        $rows = DB::connection('legacy_source')->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name=?",
            [$table]
        );

        return $rows !== [];
    }

    private function pointEnvToLedger(): void
    {
        $envPath = base_path('.env');
        if (! File::exists($envPath)) {
            return;
        }

        $env = File::get($envPath);
        $updated = preg_replace(
            '/^DB_DATABASE=.*database\.sqlite.*$/m',
            'DB_DATABASE=database/ledger.sqlite',
            $env,
            1
        );
        if (is_string($updated) && $updated !== $env) {
            File::put($envPath, $updated);
            $this->line('DB_DATABASE in .env wijst nu naar database/ledger.sqlite');
        }
    }

    private function checkpoint(string $connection): void
    {
        try {
            DB::connection($connection)->statement('PRAGMA wal_checkpoint(FULL)');
        } catch (\Throwable) {
            //
        }
    }
}
