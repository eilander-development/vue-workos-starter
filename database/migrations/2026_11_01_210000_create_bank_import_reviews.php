<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_import_reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('key')->unique();
            $table->string('identity_hash', 64)->index();
            $table->string('content_hash', 64)->unique();
            $table->string('status', 20)->default('open')->index();
            $table->json('incoming_payload');
            $table->json('existing_keys');
            $table->string('resolved_key')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('seen_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_import_reviews');
    }
};
