<?php

use App\Models\Transaction;
use App\Models\User;
use App\Services\EnabledBankingTransactionImporter;
use App\Support\BankTransactionIdentity;
use Illuminate\Support\Str;

function duplicateBankRow(array $overrides = []): Transaction
{
    return Transaction::create(array_replace([
        'key' => 'tx-'.Str::uuid(),
        'source_hash' => hash('sha256', (string) Str::uuid()),
        'source_type' => 'api',
        'date' => '2026-09-24',
        'description' => 'Naar Oranje spaarrekening H13134210',
        'amount' => -500,
        'account_iban' => 'NL00TEST1234567890',
        'type' => 'saving',
        'bank_payload' => ['raw' => ['entry_reference' => 'bank-ref-123']],
    ], $overrides));
}

test('duplicate review requires authentication', function () {
    $this->getJson('/api/sparen/duplicate-transactions')->assertUnauthorized();
    $this->deleteJson('/api/sparen/duplicate-transactions', ['keepId' => 1, 'removeId' => 2])->assertUnauthorized();
});

test('duplicate review groups stable bank references despite description and hash differences', function () {
    $this->actingAs(User::factory()->create());
    duplicateBankRow();
    duplicateBankRow(['description' => 'Naar Oranje Spaarrekening H13134210']);
    duplicateBankRow(['bank_payload' => ['entry_reference' => 'different-ref']]);
    duplicateBankRow(['account_iban' => 'NL00TEST0000000001']);
    $this->getJson('/api/sparen/duplicate-transactions')->assertOk()
        ->assertJsonCount(1, 'groups')->assertJsonCount(2, 'groups.0.rows');
});

test('removal preserves the selected original and rejects stale requests', function () {
    $this->actingAs(User::factory()->create());
    $keep = duplicateBankRow();
    $remove = duplicateBankRow();
    $this->deleteJson('/api/sparen/duplicate-transactions', ['keepId' => $keep->id, 'removeId' => $remove->id])
        ->assertOk()->assertJsonPath('deletedKey', $remove->key);
    expect(Transaction::find($keep->id))->not->toBeNull()
        ->and(Transaction::find($remove->id))->toBeNull();
    $this->deleteJson('/api/sparen/duplicate-transactions', ['keepId' => $keep->id, 'removeId' => $remove->id])->assertStatus(409);
});

test('same date and amount alone cannot authorize deletion', function () {
    $this->actingAs(User::factory()->create());
    $keep = duplicateBankRow();
    $remove = duplicateBankRow(['bank_payload' => ['entry_reference' => 'other-payment']]);
    $this->deleteJson('/api/sparen/duplicate-transactions', ['keepId' => $keep->id, 'removeId' => $remove->id])->assertUnprocessable();
    expect(Transaction::count())->toBe(2);
    $this->deleteJson('/api/sparen/duplicate-transactions', ['keepId' => $keep->id, 'removeId' => $keep->id])->assertUnprocessable();
});

test('nested bank reference prevents reimport of a legacy description-hash row', function () {
    $keep = duplicateBankRow();
    $result = app(EnabledBankingTransactionImporter::class)->import([[
        'date' => '2026-09-24',
        'booking_date' => '2026-09-24',
        'description' => 'Naar Oranje Spaarrekening H13134210',
        'amount' => -500,
        'account_iban' => $keep->account_iban,
        'raw' => ['raw' => ['entry_reference' => 'bank-ref-123']],
    ]]);
    expect($result['duplicates'])->toBe(1)->and($result['imported'])->toBe(0)
        ->and(Transaction::count())->toBe(1);
});

test('unreferenced and pending rows are not confirmed duplicates', function () {
    $row = duplicateBankRow(['bank_payload' => null]);
    expect(BankTransactionIdentity::key($row))->toBeNull();
    $pending = duplicateBankRow(['is_pending' => true]);
    expect(BankTransactionIdentity::key($pending))->toBeNull();
});

test('generic payment references are not sufficient evidence for deletion', function () {
    $row = duplicateBankRow(['bank_payload' => ['reference' => 'monthly-deposit']]);
    expect(BankTransactionIdentity::key($row))->toBeNull();
});

test('new imports keep separate payments and accounts but ignore repeat delivery', function () {
    $base = [
        'booking_date' => '2026-09-24',
        'description' => 'Transfer',
        'amount' => -500,
        'account_iban' => 'NL00TEST1234567890',
        'raw' => ['entry_reference' => 'first-ref'],
    ];
    $importer = app(EnabledBankingTransactionImporter::class);
    $result = $importer->import([
        $base,
        array_replace($base, ['raw' => ['entry_reference' => 'second-ref']]),
        array_replace($base, ['account_iban' => 'NL00TEST0000000001']),
        array_replace($base, ['description' => 'TRANSFER']),
    ]);
    expect($result['imported'])->toBe(3)->and($result['duplicates'])->toBe(1)
        ->and(Transaction::count())->toBe(3);
});
