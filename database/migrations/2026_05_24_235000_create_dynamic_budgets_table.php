<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dynamic_budgets', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7)->index(); // YYYY-MM
            $table->string('name');
            $table->decimal('budget', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_budgets');
    }
};

