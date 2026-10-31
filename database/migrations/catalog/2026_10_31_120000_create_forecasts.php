<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'catalog';

    public function up(): void
    {
        Schema::connection('catalog')->create('forecasts', function (Blueprint $table) {
            $table->id();
            $table->uuid('key')->unique();
            $table->uuid('copied_from')->nullable();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->string('start_month', 7);
            $table->string('source_hash', 64);
            $table->json('rules');
            $table->json('snapshot');
            $table->string('created_by')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->string('reverted_from', 7)->nullable();
            $table->timestamps();
        });

        Schema::connection('catalog')->create('budget_amount_changes', function (Blueprint $table) {
            $table->id();
            $table->uuid('key')->unique();
            $table->uuid('forecast_key')->index();
            $table->string('rule_id');
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->string('effective_month', 7);
            $table->string('end_month', 7)->nullable();
            $table->json('before_amounts');
            $table->json('amounts');
            $table->json('scheduled_months');
            $table->json('known_values');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('catalog')->dropIfExists('budget_amount_changes');
        Schema::connection('catalog')->dropIfExists('forecasts');
    }
};
