<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->indexExists('transactions', 'transactions_source_hash_unique')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->unique('source_hash');
        });
    }

    public function down(): void
    {
        if (! $this->indexExists('transactions', 'transactions_source_hash_unique')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['source_hash']);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $database = DB::getDatabaseName();
        $results = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $indexName]
        );

        return $results !== [];
    }
};
