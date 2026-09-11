<?php

namespace App\Console\Commands;

use App\Services\DataBackupService;
use Illuminate\Console\Command;

class DataExportCommand extends Command
{
    protected $signature = 'data:export {--path= : Pad naar het zip-bestand}';

    protected $description = 'Exporteert catalogus en ledger naar een zip-backup.';

    public function handle(DataBackupService $backups): int
    {
        $path = $this->option('path') ?: $backups->newExportPath();
        $plan = $backups->exportPlan();
        $bar = $this->output->createProgressBar(max(1, count($plan)));
        $bar->start();

        $result = $backups->exportTo($path, function () use ($bar) {
            $bar->advance();
        });

        $bar->finish();
        $this->newLine();
        $this->info('Backup: '.$result['path']);

        return self::SUCCESS;
    }
}
