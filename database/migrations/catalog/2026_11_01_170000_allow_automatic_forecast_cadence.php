<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'catalog';

    public function up(): void
    {
        Schema::connection('catalog')->table('budgets', fn (Blueprint $table) => $table->boolean('forecast_monthly')->nullable()->default(null)->change());
        DB::connection('catalog')->table('budgets')->where('forecast_monthly', false)->update(['forecast_monthly' => null]);
        // Retain choices that were explicitly remembered rather than defaulted.
        foreach (DB::connection('catalog')->table('forecasts')->orderBy('id')->get(['snapshot']) as $forecast) {
            foreach (json_decode($forecast->snapshot, true)['settings'] ?? [] as $setting) {
                if (($setting['rememberBasis'] ?? false) && array_key_exists('everyMonth', $setting)) {
                    DB::connection('catalog')->table('budgets')->where('key', $setting['itemId'])->update(['forecast_monthly' => $setting['everyMonth']]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::connection('catalog')->table('budgets')->whereNull('forecast_monthly')->update(['forecast_monthly' => false]);
        Schema::connection('catalog')->table('budgets', fn (Blueprint $table) => $table->boolean('forecast_monthly')->nullable(false)->default(false)->change());
    }
};
