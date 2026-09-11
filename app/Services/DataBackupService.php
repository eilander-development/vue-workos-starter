<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Phar;
use PharData;
use RuntimeException;

class DataBackupService
{
    public const FORMAT = 'finance-backup';

    public const VERSION = 1;

    /** @var list<string> */
    private array $skipTables = [
        'migrations',
        'sqlite_sequence',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
    ];

    /** @var array<string, list<string>> */
    private array $preferredOrder = [
        'catalog' => [
            'categories',
            'budgets',
            'import_rules',
            'savings_goals',
            'budget_month_values',
        ],
        'ledger' => [
            'users',
            'enable_banking_sessions',
            'bank_accounts',
            'plaid_connections',
            'transactions',
            'checking_period_snapshots',
            'dynamic_budgets',
        ],
    ];

    /**
     * @param  callable(string, string, string, int, int):void|null  $onProgress
     * @return array{path: string, filename: string, manifest: array<string, mixed>}
     */
    public function exportTo(string $zipPath, ?callable $onProgress = null): array
    {
        File::ensureDirectoryExists(dirname($zipPath), 0777);
        @chmod(dirname($zipPath), 0777);
        if (is_file($zipPath)) {
            File::delete($zipPath);
        }

        $archive = $this->createArchive($zipPath);

        $manifestConnections = [];
        $index = 0;
        $plan = $this->exportPlan();
        $total = max(1, count($plan));

        foreach ($plan as $item) {
            $index++;
            $connection = $item['connection'];
            $table = $item['table'];
            if ($onProgress) {
                $onProgress('export', $connection, $table, $index, $total);
            }

            $rows = DB::connection($connection)->table($table)->get()->map(
                fn ($row) => $this->serializeRow((array) $row)
            )->all();

            $archive->addFromString(
                "{$connection}/{$table}.json",
                (string) json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
            );
            $manifestConnections[$connection][$table] = count($rows);
        }

        $manifest = [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->timezone('Europe/Amsterdam')->toIso8601String(),
            'connections' => $manifestConnections,
        ];
        $archive->addFromString(
            'manifest.json',
            (string) json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE)
        );
        unset($archive);

        return [
            'path' => $zipPath,
            'filename' => basename($zipPath),
            'manifest' => $manifest,
        ];
    }

    /**
     * @param  callable(string, string, string, int, int):void|null  $onProgress
     * @return array<string, mixed>
     */
    public function importFrom(string $zipPath, ?callable $onProgress = null): array
    {
        if (! is_file($zipPath)) {
            throw new RuntimeException('Backup-bestand niet gevonden.');
        }

        try {
            $archive = new PharData($zipPath);
        } catch (\Throwable) {
            throw new RuntimeException('Dit bestand is geen geldige zip-backup.');
        }

        $manifest = $this->readManifest($archive);
        $payload = $this->readPayload($archive, $manifest);
        unset($archive);

        $plan = $this->importPlan($payload);
        $total = max(1, count($plan));
        $index = 0;
        $imported = [];

        try {
            $this->withoutForeignKeys(function () use ($plan, $payload, $onProgress, &$index, $total, &$imported) {
                foreach ($plan as $item) {
                    $index++;
                    $connection = $item['connection'];
                    $table = $item['table'];
                    if ($onProgress) {
                        $onProgress('import', $connection, $table, $index, $total);
                    }

                    $this->replaceTable($connection, $table, $payload[$connection][$table] ?? []);
                    $imported[$connection][$table] = count($payload[$connection][$table] ?? []);
                }
            });
        } catch (\Throwable $e) {
            throw new RuntimeException('Import mislukt: '.$e->getMessage(), 0, $e);
        }

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'connections' => $imported,
        ];
    }

    public function newExportPath(): string
    {
        $stamp = now()->timezone('Europe/Amsterdam')->format('Y-m-d-His');

        return storage_path('app/private/backups/finance-backup-'.$stamp.'.zip');
    }

    /**
     * @return list<array{connection: string, table: string}>
     */
    public function exportPlan(): array
    {
        $plan = [];
        foreach (['catalog', 'ledger'] as $connection) {
            foreach ($this->tablesFor($connection) as $table) {
                $plan[] = ['connection' => $connection, 'table' => $table];
            }
        }

        return $plan;
    }

    /**
     * @param  array<string, array<string, list<array<string, mixed>>>>  $payload
     * @return list<array{connection: string, table: string}>
     */
    private function importPlan(array $payload): array
    {
        $plan = [];
        foreach (['catalog', 'ledger'] as $connection) {
            foreach ($this->tablesFor($connection) as $table) {
                $plan[] = ['connection' => $connection, 'table' => $table];
            }
            foreach (array_keys($payload[$connection] ?? []) as $table) {
                if (in_array($table, $this->skipTables, true)) {
                    continue;
                }
                if (! Schema::connection($connection)->hasTable($table)) {
                    continue;
                }
                $already = array_column(array_filter($plan, fn ($item) => $item['connection'] === $connection), 'table');
                if (! in_array($table, $already, true)) {
                    $plan[] = ['connection' => $connection, 'table' => $table];
                }
            }
        }

        return $plan;
    }

    /**
     * @return list<string>
     */
    private function tablesFor(string $connection): array
    {
        $existing = $this->existingTables($connection);
        $ordered = array_values(array_filter(
            $this->preferredOrder[$connection] ?? [],
            fn (string $table) => in_array($table, $existing, true)
        ));
        $extra = array_values(array_diff($existing, $ordered));

        return array_merge($ordered, $extra);
    }

    /**
     * @return list<string>
     */
    private function existingTables(string $connection): array
    {
        $db = DB::connection($connection);
        $driver = $db->getDriverName();
        $names = [];

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            foreach ($db->select('SHOW TABLES') as $row) {
                $values = array_values((array) $row);
                $name = $values[0] ?? null;
                if (is_string($name)) {
                    $names[] = $name;
                }
            }
        } else {
            foreach ($db->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'") as $row) {
                $name = $row->name ?? null;
                if (is_string($name)) {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_filter(
            $names,
            fn (string $name) => ! in_array($name, $this->skipTables, true)
        ));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function serializeRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $row[$key] = $value->format('Y-m-d H:i:s');
            }
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    private function prepareInsertRow(array $row, array $columns): array
    {
        $payload = [];
        foreach ($columns as $column) {
            if (! array_key_exists($column, $row)) {
                continue;
            }
            $value = $row[$column];
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            }
            $payload[$column] = $value;
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function replaceTable(string $connection, string $table, array $rows): void
    {
        $db = DB::connection($connection);
        $columns = Schema::connection($connection)->getColumnListing($table);
        $db->table($table)->delete();

        foreach (array_chunk($rows, 500) as $chunk) {
            $insert = [];
            foreach ($chunk as $row) {
                $prepared = $this->prepareInsertRow($row, $columns);
                if ($prepared !== []) {
                    $insert[] = $prepared;
                }
            }
            if ($insert !== []) {
                $db->table($table)->insert($insert);
            }
        }

        if (in_array('id', $columns, true) && in_array($db->getDriverName(), ['mysql', 'mariadb'], true)) {
            $max = (int) $db->table($table)->max('id');
            $quoted = str_replace('`', '', $table);
            $db->statement('ALTER TABLE `'.$quoted.'` AUTO_INCREMENT = '.($max + 1));
        }
    }

    /**
     * @param  callable():void  $callback
     */
    private function withoutForeignKeys(callable $callback): void
    {
        foreach (['catalog', 'ledger'] as $connection) {
            $this->setForeignKeyChecks($connection, false);
        }

        try {
            $callback();
        } finally {
            foreach (['catalog', 'ledger'] as $connection) {
                $this->setForeignKeyChecks($connection, true);
            }
        }
    }

    private function setForeignKeyChecks(string $connection, bool $enabled): void
    {
        $db = DB::connection($connection);
        if ($db->getDriverName() === 'sqlite') {
            $db->statement('PRAGMA foreign_keys = '.($enabled ? 'ON' : 'OFF'));

            return;
        }

        $db->statement('SET FOREIGN_KEY_CHECKS='.($enabled ? '1' : '0'));
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(PharData $archive): array
    {
        $raw = $this->archiveFile($archive, 'manifest.json');
        if ($raw === null || $raw === '') {
            throw new RuntimeException('Backup mist manifest.json.');
        }

        $manifest = json_decode($raw, true);
        if (! is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException('Dit zip-bestand is geen finance-backup.');
        }
        if ((int) ($manifest['version'] ?? 0) !== self::VERSION) {
            throw new RuntimeException('Deze backup-versie wordt niet ondersteund.');
        }

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, array<string, list<array<string, mixed>>>>
     */
    private function readPayload(PharData $archive, array $manifest): array
    {
        $payload = ['catalog' => [], 'ledger' => []];
        $connections = $manifest['connections'] ?? [];
        if (! is_array($connections)) {
            throw new RuntimeException('Ongeldig backup-manifest.');
        }

        foreach (['catalog', 'ledger'] as $connection) {
            $tables = $connections[$connection] ?? [];
            if (! is_array($tables)) {
                continue;
            }
            foreach (array_keys($tables) as $table) {
                if (! is_string($table) || in_array($table, $this->skipTables, true)) {
                    continue;
                }
                $raw = $this->archiveFile($archive, "{$connection}/{$table}.json");
                if ($raw === null) {
                    $payload[$connection][$table] = [];

                    continue;
                }
                $rows = json_decode($raw, true);
                if (! is_array($rows)) {
                    throw new RuntimeException("Ongeldige data in {$connection}.{$table}.");
                }
                $payload[$connection][$table] = array_values(array_filter(
                    $rows,
                    fn ($row) => is_array($row)
                ));
            }
        }

        return $payload;
    }

    private function createArchive(string $zipPath): PharData
    {
        try {
            return new PharData($zipPath, 0, 'finance-backup.zip', Phar::ZIP);
        } catch (\Throwable $e) {
            throw new RuntimeException('Kon het backup-bestand niet aanmaken.', 0, $e);
        }
    }

    private function archiveFile(PharData $archive, string $name): ?string
    {
        if (! isset($archive[$name])) {
            return null;
        }

        $file = $archive[$name];

        return $file->getContent();
    }
}
