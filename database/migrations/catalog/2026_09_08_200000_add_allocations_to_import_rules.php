<?php

use App\Models\Budget;
use App\Models\ImportRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'catalog';

    public function up(): void
    {
        if (! Schema::connection('catalog')->hasColumn('import_rules', 'allocations')) {
            Schema::connection('catalog')->table('import_rules', function (Blueprint $table) {
                $table->json('allocations')->nullable();
            });
        }

        $woon = Budget::query()->where('key', 'verz-2')->first();
        $auto = Budget::query()->where('key', 'verv-2')->first();
        $rule = ImportRule::query()->where('key', 'rule-14')->first();

        if (! $woon || ! $auto || ! $rule) {
            return;
        }

        $rule->allocations = [
            ['budgetItemId' => 'verz-2', 'amount' => round((float) $woon->budget, 2)],
            ['budgetItemId' => 'verv-2', 'amount' => round((float) $auto->budget, 2)],
        ];
        $rule->save();
    }

    public function down(): void
    {
        if (! Schema::connection('catalog')->hasColumn('import_rules', 'allocations')) {
            return;
        }

        Schema::connection('catalog')->table('import_rules', function (Blueprint $table) {
            $table->dropColumn('allocations');
        });
    }
};
