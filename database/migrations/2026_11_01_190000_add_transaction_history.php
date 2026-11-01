<?php

use App\Support\BankTransactionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
            $table->string('archive_reason')->nullable();
            $table->string('superseded_by_key')->nullable()->index();
            $table->json('lifecycle_metadata')->nullable();
        });
        // Older imports sometimes stored PDNG in the payload without setting the flag.
        DB::table('transactions')->where('source_type', 'api')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $payload = is_string($row->bank_payload) ? json_decode($row->bank_payload, true) : [];
                if (BankTransactionStatus::isPending(is_array($payload) ? $payload : [])) {
                    DB::table('transactions')->where('id', $row->id)->update(['is_pending' => true]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', fn (Blueprint $table) => $table->dropColumn(['archived_at', 'archive_reason', 'superseded_by_key', 'lifecycle_metadata']));
    }
};
