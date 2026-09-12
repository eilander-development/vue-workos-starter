<?php

namespace App\Services;

use App\Support\BackupZip;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
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

    /** @var array<string, list<list<string>>> */
    private array $identityColumns = [
        'categories' => [['key'], ['slug']],
        'budgets' => [['key']],
        'import_rules' => [['key']],
        'savings_goals' => [['key']],
        'budget_month_values' => [['budget_id', 'month_id', 'year']],
        'users' => [['email']],
        'transactions' => [['source_hash'], ['key']],
        'bank_accounts' => [['key'], ['enable_banking_uid']],
        'enable_banking_sessions' => [['session_id']],
        'plaid_connections' => [['item_id']],
        'checking_period_snapshots' => [['year', 'month_id']],
        'dynamic_budgets' => [['month', 'name']],
    ];

    /** @var array<string, 'live'|'backup'> */
    private array $defaultResolution = [
        'categories' => 'backup',
        'budgets' => 'backup',
        'import_rules' => 'backup',
        'savings_goals' => 'backup',
        'budget_month_values' => 'backup',
        'users' => 'live',
        'transactions' => 'live',
        'bank_accounts' => 'live',
        'enable_banking_sessions' => 'live',
        'plaid_connections' => 'live',
        'checking_period_snapshots' => 'live',
        'dynamic_budgets' => 'live',
    ];

    /** @var list<string> */
    private array $protectNewFromBackup = [
        'enable_banking_sessions',
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

        $archive = new BackupZip;

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

            $archive->add(
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
        $archive->add(
            'manifest.json',
            (string) json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE)
        );
        $archive->save($zipPath);

        return [
            'path' => $zipPath,
            'filename' => basename($zipPath),
            'manifest' => $manifest,
        ];
    }

    /**
     * @param  callable(string, string, string, int, int):void|null  $onProgress
     * @param  array{preview?: bool, replace?: bool, resolutions?: array<string, 'live'|'backup'>}  $options
     * @return array<string, mixed>
     */
    public function importFrom(string $zipPath, ?callable $onProgress = null, array $options = []): array
    {
        if (! is_file($zipPath)) {
            throw new RuntimeException('Backup-bestand niet gevonden.');
        }

        try {
            $archive = BackupZip::open($zipPath);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable) {
            throw new RuntimeException('Dit bestand is geen geldige zip-backup.');
        }

        $manifest = $this->readManifest($archive);
        $payload = $this->readPayload($archive, $manifest);
        $preview = (bool) ($options['preview'] ?? false);
        $replace = (bool) ($options['replace'] ?? false);
        /** @var array<string, 'live'|'backup'> $resolutions */
        $resolutions = $options['resolutions'] ?? [];

        $plan = $this->importPlan($payload);
        $total = max(1, count($plan));
        $index = 0;
        $tables = [];

        $apply = function () use ($plan, $payload, $onProgress, &$index, $total, &$tables, $preview, $replace, $resolutions) {
            foreach ($plan as $item) {
                $index++;
                $connection = $item['connection'];
                $table = $item['table'];
                if ($onProgress) {
                    $onProgress($preview ? 'preview' : 'import', $connection, $table, $index, $total);
                }

                $rows = $payload[$connection][$table] ?? [];
                if ($replace && ! $preview) {
                    $this->replaceTable($connection, $table, $rows);
                    $tables[] = [
                        'connection' => $connection,
                        'table' => $table,
                        'insert' => count($rows),
                        'skip' => 0,
                        'conflict' => 0,
                        'protected' => 0,
                        'resolution' => 'backup',
                    ];

                    continue;
                }

                $tables[] = $this->mergeTable(
                    $connection,
                    $table,
                    $rows,
                    $this->resolutionFor($connection, $table, $resolutions),
                    apply: ! $preview
                );
            }
        };

        try {
            if ($preview) {
                $apply();
            } else {
                $this->withoutForeignKeys($apply);
            }
        } catch (\Throwable $e) {
            throw new RuntimeException('Import mislukt: '.$e->getMessage(), 0, $e);
        }

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'mode' => $replace ? 'replace' : 'merge',
            'preview' => $preview,
            'tables' => $tables,
            'has_conflicts' => collect($tables)->contains(fn (array $row) => ($row['conflict'] ?? 0) > 0),
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
                if (in_array($table, $this->tablesOwnedByOther($connection), true)) {
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
        $otherOwned = $this->tablesOwnedByOther($connection);
        $ordered = array_values(array_filter(
            $this->preferredOrder[$connection] ?? [],
            fn (string $table) => in_array($table, $existing, true)
        ));
        $extra = array_values(array_diff($existing, $ordered, $otherOwned));

        return array_merge($ordered, $extra);
    }

    /**
     * @return list<string>
     */
    private function tablesOwnedByOther(string $connection): array
    {
        $owned = [];
        foreach ($this->preferredOrder as $name => $tables) {
            if ($name !== $connection) {
                $owned = array_merge($owned, $tables);
            }
        }

        return $owned;
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
     * @param  'live'|'backup'  $resolution
     * @return array{connection: string, table: string, insert: int, skip: int, conflict: int, protected: int, resolution: string, examples: list<string>}
     */
    private function mergeTable(string $connection, string $table, array $rows, string $resolution, bool $apply): array
    {
        $db = DB::connection($connection);
        $columns = Schema::connection($connection)->getColumnListing($table);
        $existing = $db->table($table)->get()->map(fn ($row) => (array) $row)->all();
        $index = $this->buildIdentityIndex($table, $existing);
        $usedIds = [];
        foreach ($existing as $row) {
            if (isset($row['id'])) {
                $usedIds[(string) $row['id']] = true;
            }
        }

        $insert = 0;
        $skip = 0;
        $conflict = 0;
        $protected = 0;
        $examples = [];
        $insertRows = [];
        $updateRows = [];

        $protectNew = in_array($table, $this->protectNewFromBackup, true)
            && $existing !== []
            && $resolution === 'live';

        foreach ($rows as $row) {
            $prepared = $this->prepareInsertRow($row, $columns);
            if ($prepared === []) {
                continue;
            }

            $match = $this->findExistingRow($table, $prepared, $index);
            if ($match === null) {
                if ($protectNew) {
                    $protected++;
                    if (count($examples) < 5) {
                        $examples[] = $this->rowLabel($table, $prepared).' (niet toegevoegd: live heeft al een banksessie)';
                    }

                    continue;
                }

                if (isset($prepared['id']) && isset($usedIds[(string) $prepared['id']])) {
                    unset($prepared['id']);
                }
                $insert++;
                $insertRows[] = $prepared;

                continue;
            }

            if (! $this->rowsDiffer($match, $prepared, $columns)) {
                $skip++;

                continue;
            }

            $conflict++;
            if (count($examples) < 5) {
                $examples[] = $this->rowLabel($table, $prepared);
            }

            if ($resolution === 'backup') {
                $update = $prepared;
                if (isset($match['id'])) {
                    $update['id'] = $match['id'];
                }
                $updateRows[] = $update;
            }
        }

        if ($apply) {
            foreach (array_chunk($insertRows, 500) as $chunk) {
                $db->table($table)->insert($chunk);
            }
            foreach ($updateRows as $update) {
                if (! isset($update['id'])) {
                    continue;
                }
                $id = $update['id'];
                unset($update['id']);
                $db->table($table)->where('id', $id)->update($update);
            }
            if ($insertRows !== [] && in_array('id', $columns, true) && in_array($db->getDriverName(), ['mysql', 'mariadb'], true)) {
                $max = (int) $db->table($table)->max('id');
                $quoted = str_replace('`', '', $table);
                $db->statement('ALTER TABLE `'.$quoted.'` AUTO_INCREMENT = '.($max + 1));
            }
        }

        return [
            'connection' => $connection,
            'table' => $table,
            'insert' => $insert,
            'skip' => $skip,
            'conflict' => $conflict,
            'protected' => $protected,
            'resolution' => $resolution,
            'examples' => $examples,
        ];
    }

    /**
     * @param  array<string, 'live'|'backup'>  $resolutions
     * @return 'live'|'backup'
     */
    private function resolutionFor(string $connection, string $table, array $resolutions): string
    {
        $key = $connection.'.'.$table;
        $chosen = $resolutions[$key] ?? $this->defaultResolution[$table] ?? 'live';

        return $chosen === 'backup' ? 'backup' : 'live';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function buildIdentityIndex(string $table, array $rows): array
    {
        $index = [];
        foreach ($rows as $row) {
            foreach ($this->identityColumns[$table] ?? [['id']] as $columns) {
                $value = $this->identityValue($row, $columns);
                if ($value !== null) {
                    $index[$value] = $row;
                }
            }
        }

        return $index;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $index
     * @return array<string, mixed>|null
     */
    private function findExistingRow(string $table, array $row, array $index): ?array
    {
        foreach ($this->identityColumns[$table] ?? [['id']] as $columns) {
            $value = $this->identityValue($row, $columns);
            if ($value !== null && isset($index[$value])) {
                return $index[$value];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $columns
     */
    private function identityValue(array $row, array $columns): ?string
    {
        $parts = [];
        foreach ($columns as $column) {
            $value = $row[$column] ?? null;
            if ($value === null || $value === '') {
                return null;
            }
            $parts[] = $column.'='.$value;
        }

        return implode('|', $parts);
    }

    /**
     * @param  array<string, mixed>  $live
     * @param  array<string, mixed>  $backup
     * @param  list<string>  $columns
     */
    private function rowsDiffer(array $live, array $backup, array $columns): bool
    {
        $ignore = ['created_at', 'updated_at', 'remember_token', 'password'];
        foreach ($columns as $column) {
            if (in_array($column, $ignore, true)) {
                continue;
            }
            if ($this->normalizeCompareValue($live[$column] ?? null) !== $this->normalizeCompareValue($backup[$column] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeCompareValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value) || is_object($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if ($value === null) {
            return '';
        }

        return is_numeric($value) ? (string) (0 + $value) : trim((string) $value);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowLabel(string $table, array $row): string
    {
        foreach (['key', 'email', 'name', 'session_id', 'description', 'iban'] as $column) {
            $value = $row[$column] ?? null;
            if (is_string($value) && $value !== '') {
                return $table.': '.$value;
            }
        }

        return $table;
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
    private function readManifest(BackupZip $archive): array
    {
        $raw = $archive->get('manifest.json');
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
    private function readPayload(BackupZip $archive, array $manifest): array
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
                $raw = $archive->get("{$connection}/{$table}.json");
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
}
