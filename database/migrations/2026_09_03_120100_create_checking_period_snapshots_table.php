<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checking_period_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('month_id', 8);
            $table->date('period_end');
            $table->decimal('balance', 15, 2);
            $table->string('source', 32)->default('reconstructed');
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
            $table->unique(['year', 'month_id']);
            $table->unique('period_end');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checking_period_snapshots');
    }
};
