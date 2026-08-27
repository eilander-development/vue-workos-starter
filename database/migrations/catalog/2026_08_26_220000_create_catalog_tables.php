<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'catalog';

    public function up(): void
    {
        Schema::connection('catalog')->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('key')->nullable()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('type')->default('expense');
            $table->timestamps();
        });

        Schema::connection('catalog')->create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('key')->nullable()->unique();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('budget', 15, 2);
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::connection('catalog')->create('import_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->nullable()->unique();
            $table->string('name')->nullable();
            $table->string('type');
            $table->string('match_value');
            $table->string('match_field')->default('description');
            $table->boolean('is_active')->default(true);
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::connection('catalog')->create('savings_goals', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('account_iban');
            $table->string('bank_name')->nullable();
            $table->decimal('target_amount', 15, 2)->default(0);
            $table->decimal('initial_amount', 15, 2)->default(0);
            $table->decimal('monthly_contribution', 15, 2)->default(0);
            $table->string('color')->nullable();
            $table->string('icon_name')->nullable();
            $table->text('notes')->nullable();
            $table->string('budget_key')->nullable();
            $table->string('kind', 16)->default('goal');
            $table->json('budget_keys')->nullable();
            $table->timestamps();
        });

        Schema::connection('catalog')->create('budget_month_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->string('month_id', 8);
            $table->unsignedSmallInteger('year')->default(2026);
            $table->decimal('estimated', 15, 2)->default(0);
            $table->json('entries')->nullable();
            $table->timestamps();
            $table->unique(['budget_id', 'month_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::connection('catalog')->dropIfExists('budget_month_values');
        Schema::connection('catalog')->dropIfExists('savings_goals');
        Schema::connection('catalog')->dropIfExists('import_rules');
        Schema::connection('catalog')->dropIfExists('budgets');
        Schema::connection('catalog')->dropIfExists('categories');
    }
};
