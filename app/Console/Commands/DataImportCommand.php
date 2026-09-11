<?php

namespace App\Console\Commands;

use App\Services\DataBackupService;
use Illuminate\Console\Command;

class DataImportCommand extends Command
{
    protected $signature = 'data:import {path : Pad naar de zip-backup} {--force : Overslaan van de bevestiging}';

    protected $description = 'Overschrijft catalogus en ledger met een zip-backup.';

    public function handle(DataBackupService $backups): int
    {
        $path = $this->argument('path');
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            $this->error('Backup-bestand niet gevonden.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Dit overschrijft alle data in catalogus en ledger. Doorgaan?')) {
            $this->warn('Import afgebroken.');

            return self::SUCCESS;
        }

        $summary = $backups->importFrom($path, function (string $phase, string $connection, string $table) {
            $this->line("{$connection}.{$table}");
        });

        $this->info('Import klaar.');
        foreach ($summary['connections'] as $connection => $tables) {
            foreach ($tables as $table => $count) {
                $this->line("{$connection}.{$table}: {$count}");
            }
        }

        return self::SUCCESS;
    }
}
