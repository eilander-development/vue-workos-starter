<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'catalog';

    public function up(): void
    {
        Schema::connection('catalog')->table('budgets', function (Blueprint $table) {
            $table->string('forecast_basis', 8)->default('latest');
            $table->boolean('forecast_monthly')->default(false);
        });
    }

    public function down(): void
    {
        Schema::connection('catalog')->table('budgets', fn (Blueprint $table) => $table->dropColumn(['forecast_basis', 'forecast_monthly']));
    }
};
