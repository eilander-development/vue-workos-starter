<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('workos_id')->unique();
            $table->rememberToken();
            $table->text('avatar');
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->nullable()->unique();
            $table->string('source_hash', 64)->nullable()->unique();
            $table->string('source_type', 20)->nullable();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->unsignedBigInteger('budget_id')->nullable()->index();
            $table->unsignedBigInteger('rule_id')->nullable()->index();
            $table->string('type')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('description');
            $table->string('counterparty_iban')->nullable();
            $table->string('account_iban')->nullable();
            $table->string('counterparty_name')->nullable();
            $table->boolean('is_pending')->default(false);
            $table->string('booked_time')->nullable();
            $table->boolean('link_excluded')->default(false);
            $table->string('link_exclusion_reason')->nullable();
            $table->date('date');
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->timestamps();
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

        Schema::create('enable_banking_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->string('status')->default('authorized');
            $table->timestamp('valid_until')->nullable();
            $table->string('aspsp_name')->nullable();
            $table->string('aspsp_country', 2)->nullable();
            $table->json('accounts')->nullable();
            $table->timestamps();
        });

        Schema::create('plaid_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('item_id');
            $table->string('institution_name')->nullable();
            $table->text('access_token');
            $table->timestamps();
        });

        Schema::create('dynamic_budgets', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7)->index();
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
        Schema::dropIfExists('plaid_connections');
        Schema::dropIfExists('enable_banking_sessions');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
