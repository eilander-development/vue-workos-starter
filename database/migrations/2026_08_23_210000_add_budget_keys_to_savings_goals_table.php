<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('savings_goals')) {
            return;
        }

        Schema::table('savings_goals', function (Blueprint $table) {
            if (! Schema::hasColumn('savings_goals', 'budget_keys')) {
                $table->json('budget_keys')->nullable()->after('budget_key');
            }
        });

        if (Schema::hasColumn('savings_goals', 'budget_key')) {
            $rows = DB::table('savings_goals')
                ->whereNotNull('budget_key')
                ->where('budget_key', '!=', '')
                ->get();

            foreach ($rows as $row) {
                $existing = json_decode((string) ($row->budget_keys ?? ''), true);
                if (is_array($existing) && $existing !== []) {
                    continue;
                }

                DB::table('savings_goals')->where('id', $row->id)->update([
                    'budget_keys' => json_encode([(string) $row->budget_key]),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('savings_goals')) {
            return;
        }

        Schema::table('savings_goals', function (Blueprint $table) {
            if (Schema::hasColumn('savings_goals', 'budget_keys')) {
                $table->dropColumn('budget_keys');
            }
        });
    }
};
