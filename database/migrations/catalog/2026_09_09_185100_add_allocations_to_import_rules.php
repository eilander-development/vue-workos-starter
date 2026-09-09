<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'catalog';

    public function up(): void
    {
        if (Schema::connection('catalog')->hasColumn('import_rules', 'allocations')) {
            return;
        }

        Schema::connection('catalog')->table('import_rules', function (Blueprint $table) {
            $table->json('allocations')->nullable();
        });
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
