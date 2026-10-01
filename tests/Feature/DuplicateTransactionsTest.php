<?php

use App\DTOs\TransactionDTO;
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

test('missing stored accounts are recovered from incoming bank payloads', function () {
    $this->actingAs(User::factory()->create());
    $payload = ['raw' => ['entry_reference' => 'incoming-ref', 'creditor_account' => ['iban' => 'NL00TEST1234567890']]];
    $keep = duplicateBankRow(['amount' => 46.31, 'bank_payload' => $payload]);
    $remove = duplicateBankRow(['amount' => 46.31, 'account_iban' => null, 'bank_payload' => $payload]);
    $this->getJson('/api/sparen/duplicate-transactions')->assertOk()->assertJsonCount(1, 'groups');
    $this->deleteJson('/api/sparen/duplicate-transactions', ['keepId' => $keep->id, 'removeId' => $remove->id])->assertOk();
});

test('two missing accounts can still be identified without guessing the counterparty', function () {
    $payload = ['raw' => ['entry_reference' => 'outgoing-ref', 'debtor_account' => ['iban' => 'NL00TEST1234567890'], 'creditor_account' => ['iban' => 'NL00TEST9999999999']]];
    $first = duplicateBankRow(['account_iban' => null, 'bank_payload' => $payload]);
    $second = duplicateBankRow(['account_iban' => null, 'bank_payload' => $payload]);
    expect(BankTransactionIdentity::key($first))->not->toBeNull()
        ->and(BankTransactionIdentity::key($first))->toBe(BankTransactionIdentity::key($second))
        ->and(BankTransactionIdentity::ownAccount($payload, -500))->toBe('NL00TEST1234567890')
        ->and(BankTransactionIdentity::ownAccount($payload, 500))->toBe('NL00TEST9999999999')
        ->and(BankTransactionIdentity::ownAccount(['creditor_account' => ['iban' => 'NL00TEST9999999999']], -500))->toBe('');
});

test('reimport with missing account matches the original bank payload', function () {
    $payload = ['raw' => ['entry_reference' => 'reimport-ref', 'creditor_account' => ['iban' => 'NL00TEST1234567890']]];
    duplicateBankRow(['amount' => 46.31, 'account_iban' => null, 'bank_payload' => $payload]);
    $result = app(EnabledBankingTransactionImporter::class)->import([[
        'booking_date' => '2026-09-24', 'description' => 'Van Oranje spaarrekening',
        'amount' => 46.31, 'raw' => $payload,
    ]]);
    expect($result['duplicates'])->toBe(1)->and(Transaction::count())->toBe(1);
});

function strictImportRow(array $overrides = []): array
{
    return array_replace([
        'booking_date' => '2026-09-24', 'description' => 'Payment', 'amount' => -500,
        'account_iban' => 'NL00TEST1234567890', 'raw' => ['entry_reference' => 'strict-ref'],
    ], $overrides);
}

test('missing bank identity and invalid booking data are blocked without writes', function () {
    $result = app(EnabledBankingTransactionImporter::class)->import([
        strictImportRow(['raw' => []]),
        strictImportRow(['account_iban' => null]),
        strictImportRow(['booking_date' => null]),
        strictImportRow(['amount' => 'invalid']),
    ]);
    expect($result['blocked'])->toBe(4)->and($result['missing_identity'])->toBe(2)
        ->and($result['invalid'])->toBe(2)->and(Transaction::count())->toBe(0);
});

test('changed amount or date for the same reference is blocked rather than duplicated', function () {
    $importer = app(EnabledBankingTransactionImporter::class);
    $importer->import([strictImportRow()]);
    $result = $importer->import([
        strictImportRow(['amount' => -501]),
        strictImportRow(['booking_date' => '2026-09-25']),
        strictImportRow(['description' => 'PAYMENT']),
    ]);
    expect($result['conflicts'])->toBe(2)->and($result['blocked'])->toBe(2)
        ->and($result['duplicates'])->toBe(1)->and(Transaction::count())->toBe(1)
        ->and((float) Transaction::first()->amount)->toBe(-500.0);
});

test('pending bank entries wait until booked', function () {
    $importer = app(EnabledBankingTransactionImporter::class);
    $result = $importer->import([strictImportRow(['raw' => ['raw' => ['entry_reference' => 'strict-ref', 'status' => 'PDNG']]])]);
    expect($result['pending'])->toBe(1)->and(Transaction::count())->toBe(0);
    expect($importer->import([strictImportRow()])['imported'])->toBe(1);
});

test('unique-key collision is recovered when the row was absent from the legacy index', function () {
    duplicateBankRow([
        'source_hash' => hash('sha256', json_encode(['enablebanking-v2', 'NL00TEST1234567890', 'strict-ref'])),
        'bank_payload' => null,
    ]);
    // No payload means the initial identity index cannot find it. The insert
    // must hit the unique index and recover the stored row via createOrFirst.
    $result = app(EnabledBankingTransactionImporter::class)->import([strictImportRow()]);
    expect($result['duplicates'])->toBe(1)->and($result['imported'])->toBe(0)
        ->and(Transaction::count())->toBe(1);
});

test('bank normalization selects the receiving account for incoming money', function () {
    $raw = [
        'entry_reference' => 'incoming-dto',
        'credit_debit_indicator' => 'CRDT',
        'transaction_amount' => ['amount' => '20.00', 'currency' => 'EUR'],
        'booking_date' => '2026-09-24',
        'creditor_account' => ['iban' => 'NL00TEST1234567890'],
        'debtor_account' => ['iban' => 'NL00TEST9999999999'],
    ];
    $row = TransactionDTO::fromEnableBanking($raw)->toArray();
    expect($row['account_id'])->toBe('NL00TEST1234567890');
    // Older normalized wrappers could incorrectly use the debtor as account_id.
    $row['account_id'] = 'NL00TEST9999999999';
    expect(BankTransactionIdentity::ownAccount($row, 20))->toBe('NL00TEST1234567890');
    unset($raw['booking_date']);
    $undated = TransactionDTO::fromEnableBanking($raw)->toArray();
    $result = app(EnabledBankingTransactionImporter::class)->import([$undated]);
    expect($result['invalid'])->toBe(1)->and(Transaction::count())->toBe(0);
});
