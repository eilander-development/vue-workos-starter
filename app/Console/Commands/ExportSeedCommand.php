<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ExportSeedCommand extends Command
{
    protected $signature = 'db:export-seed {table?} {--path=database/seeders/exports} {--force}';
    protected $description = 'Export table(s) to JSON seed files for later import';

    public function handle()
    {
        $table = $this->argument('table');
        $path = rtrim($this->option('path'), "/\\");

        if (! File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }

        $tables = [];

        if ($table) {
            $tables = [$table];
        } else {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                $rows = DB::select('SHOW TABLES');
                $key = 'Tables_in_' . DB::getDatabaseName();
                foreach ($rows as $r) {
                    $obj = (array) $r;
                    $tables[] = $obj[$key];
                }
            } elseif ($driver === 'sqlite') {
                $rows = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
                foreach ($rows as $r) {
                    $obj = (array) $r;
                    $name = $obj['name'] ?? null;
                    if ($name && ! in_array($name, ['sqlite_sequence', 'sqlite_stat1', 'sqlite_stat4'], true)) {
                        $tables[] = $name;
                    }
                }
            } else {
                $this->error('No table provided and automatic table discovery not supported for driver: ' . $driver);
                return 1;
            }
        }

        foreach ($tables as $t) {
            $this->info("Exporting table: {$t}");
            try {
                $rows = DB::table($t)->get()->map(function ($r) {
                    return (array) $r;
                })->all();

                $file = $path . DIRECTORY_SEPARATOR . $t . '.json';
                File::put($file, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->info("Wrote {$file} (" . count($rows) . " rows)");
            } catch (\Exception $e) {
                $this->error("Failed to export table {$t}: " . $e->getMessage());
            }
        }

        // Generate a simple seeder that will import files from that folder
        $seederPath = database_path('seeders/ExportedSeeder.php');
        if ($this->option('force') || ! File::exists($seederPath)) {
            $pathExpression = $this->makeSeederPathExpression($path);
            $seederContents = <<<PHP
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ExportedSeeder extends Seeder
{
    public function run()
    {
        \$path = {$pathExpression};
        if (! File::exists(\$path)) {
            return;
        }

        \$files = File::files(\$path);

        DB::beginTransaction();
        try {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }

            foreach (\$files as \$file) {
                \$table = pathinfo(\$file->getFilename(), PATHINFO_FILENAME);
                \$content = File::get(\$file->getPathname());
                \$rows = json_decode(\$content, true);
                if (! is_array(\$rows) || empty(\$rows)) {
                    continue;
                }

                foreach (array_chunk(\$rows, 1000) as \$chunk) {
                    DB::table(\$table)->insertOrIgnore(\$chunk);
                }
            }

            if (DB::getDriverName() === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
            DB::commit();
        } catch (\Exception \$e) {
            DB::rollBack();
            throw \$e;
        }
    }
}
PHP;

            File::put($seederPath, $seederContents);
            $this->info('Generated seeder: ' . $seederPath);
        }

        $this->info('Export complete.');
        return 0;
    }

    private function makeSeederPathExpression(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return "'" . addslashes($path) . "'";
        }

        if (str_starts_with($path, 'database/') || str_starts_with($path, 'database\\')) {
            $relative = preg_replace('#^database[\\/]#', '', $path);
            return "database_path('" . addslashes($relative) . "')";
        }

        return "base_path('" . addslashes($path) . "')";
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:|\\\\|\/)/', $path) === 1;
    }
}
