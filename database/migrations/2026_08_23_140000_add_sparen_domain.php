<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('key')->nullable()->unique()->after('id');
            $table->text('description')->nullable()->after('color');
            $table->boolean('is_default')->default(false)->after('description');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->string('key')->nullable()->unique()->after('id');
            $table->text('notes')->nullable()->after('budget');
            $table->unsignedInteger('sort_order')->default(0)->after('notes');
        });

        Schema::table('import_rules', function (Blueprint $table) {
            $table->string('key')->nullable()->unique()->after('id');
            $table->string('name')->nullable()->after('key');
            $table->string('match_field')->default('description')->after('match_value');
            $table->boolean('is_active')->default(true)->after('match_field');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('key')->nullable()->unique()->after('id');
            $table->string('account_iban')->nullable()->after('counterparty_iban');
            $table->string('counterparty_name')->nullable()->after('account_iban');
            $table->boolean('is_pending')->default(false)->after('counterparty_name');
            $table->string('booked_time')->nullable()->after('is_pending');
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('bank_name')->nullable();
            $table->string('iban')->nullable();
            $table->string('type')->default('checking');
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('available_balance', 15, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('status')->default('disconnected');
            $table->string('enable_banking_uid')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedInteger('sync_count_today')->default(0);
            $table->date('sync_count_date')->nullable();
            $table->timestamps();
        });

        Schema::create('savings_goals', function (Blueprint $table) {
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
            $table->timestamps();
        });

        Schema::create('budget_month_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->string('month_id', 8);
            $table->unsignedSmallInteger('year')->default(2026);
            $table->decimal('estimated', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['budget_id', 'month_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_month_values');
        Schema::dropIfExists('savings_goals');
        Schema::dropIfExists('bank_accounts');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['key', 'account_iban', 'counterparty_name', 'is_pending', 'booked_time']);
        });

        Schema::table('import_rules', function (Blueprint $table) {
            $table->dropColumn(['key', 'name', 'match_field', 'is_active']);
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropColumn(['key', 'notes', 'sort_order']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['key', 'description', 'is_default']);
        });
    }
};
