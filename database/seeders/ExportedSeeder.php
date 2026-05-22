<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ExportedSeeder extends Seeder
{
    public function run()
    {
        $path = database_path('seeders/exports');
        if (! File::exists($path)) {
            return;
        }

        $files = File::files($path);

        DB::beginTransaction();
        try {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }

            foreach ($files as $file) {
                $table = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $content = File::get($file->getPathname());
                $rows = json_decode($content, true);
                if (! is_array($rows) || empty($rows)) {
                    continue;
                }

                foreach (array_chunk($rows, 1000) as $chunk) {
                    DB::table($table)->insert($chunk);
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
            throw $e;
        }
    }
}
