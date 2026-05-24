<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportSeedCommand extends Command
{
    protected $signature = 'db:import-seed {--path=database/seeders/exports} {--truncate}';

    protected $description = 'Import JSON seed files from a folder into the database';

    public function handle()
    {
        $path = rtrim($this->option('path'), '/\\');

        if (! File::exists($path)) {
            $this->error('Path does not exist: '.$path);

            return 1;
        }

        $files = collect(File::files($path))
            ->sortBy(function ($file) {
                $order = [
                    'users' => 1,
                    'categories' => 2,
                    'budgets' => 3,
                    'import_rules' => 4,
                    'transactions' => 5,
                    'plaid_settings' => 6,
                    'plaid_connections' => 7,
                    'sessions' => 8,
                    'jobs' => 9,
                    'job_batches' => 10,
                    'failed_jobs' => 11,
                    'cache' => 12,
                    'cache_locks' => 13,
                    'migrations' => 14,
                ];

                $table = pathinfo($file->getFilename(), PATHINFO_FILENAME);

                return $order[$table] ?? 999;
            })
            ->values()
            ->all();

        if (empty($files)) {
            $this->info('No files found in: '.$path);

            return 0;
        }

        DB::beginTransaction();
        try {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }

            foreach ($files as $file) {
                $table = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $this->info('Importing '.$table);
                $content = File::get($file->getPathname());
                $rows = json_decode($content, true);
                if (! is_array($rows) || empty($rows)) {
                    continue;
                }

                if ($this->option('truncate')) {
                    DB::table($table)->delete();
                }

                foreach (array_chunk($rows, 1000) as $chunk) {
                    DB::table($table)->insertOrIgnore($chunk);
                }
            }

            if (DB::getDriverName() === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Import failed: '.$e->getMessage());

            return 1;
        }

        $this->info('Import complete.');

        return 0;
    }
}
