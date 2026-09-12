<?php

namespace App\Console\Commands;

use App\Services\DataBackupService;
use Illuminate\Console\Command;

class DataImportCommand extends Command
{
    protected $signature = 'data:import {path : Pad naar de zip-backup} {--force : Overslaan van de bevestiging} {--replace : Oude gedrag: tabellen leeggooien}';

    protected $description = 'Voegt backup-rijen toe. Bestaande rijen blijven; bij conflict wint live tenzij --replace.';

    public function handle(DataBackupService $backups): int
    {
        $path = $this->argument('path');
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            $this->error('Backup-bestand niet gevonden.');

            return self::FAILURE;
        }

        $replace = (bool) $this->option('replace');
        $vraag = $replace
            ? 'Dit wist tabellen en zet de zip terug. Doorgaan?'
            : 'Nieuwe rijen worden toegevoegd. Bestaande rijen blijven (live wint bij conflict). Doorgaan?';

        if (! $this->option('force') && ! $this->confirm($vraag)) {
            $this->warn('Import afgebroken.');

            return self::SUCCESS;
        }

        $summary = $backups->importFrom($path, function (string $phase, string $connection, string $table) {
            $this->line("{$connection}.{$table}");
        }, ['replace' => $replace]);

        $this->info($replace ? 'Import klaar (replace).' : 'Import klaar (merge).');
        foreach ($summary['tables'] ?? [] as $table) {
            $this->line(sprintf(
                '%s.%s: +%d nieuw, %d gelijk, %d conflict (%s)',
                $table['connection'],
                $table['table'],
                $table['insert'],
                $table['skip'],
                $table['conflict'],
                $table['resolution']
            ));
        }

        return self::SUCCESS;
    }
}
