<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_month_values', function (Blueprint $table) {
            $table->json('entries')->nullable()->after('estimated');
        });
    }

    public function down(): void
    {
        Schema::table('budget_month_values', function (Blueprint $table) {
            $table->dropColumn('entries');
        });
    }
};
