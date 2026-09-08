<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('transactions', 'allocations')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->json('allocations')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('transactions', 'allocations')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('allocations');
        });
    }
};
