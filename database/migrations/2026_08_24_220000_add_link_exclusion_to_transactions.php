<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('link_excluded')->default(false)->after('booked_time');
            $table->string('link_exclusion_reason')->nullable()->after('link_excluded');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['link_excluded', 'link_exclusion_reason']);
        });
    }
};
