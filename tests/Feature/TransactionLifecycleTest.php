<?php

use App\Models\Budget;
use App\Models\Category;
use App\Models\ImportRule;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BankSyncReport;
use App\Services\EnabledBankingTransactionImporter;
use App\Services\SparenStateService;
use App\Services\TransactionLifecycle;
use Illuminate\Support\Str;

function lifecycleBankRow(array $overrides = []): array
{
    return array_replace(['booking_date' => '2026-10-20', 'amount' => -150, 'description' => 'Insurance', 'account_iban' => 'NL00TEST1234567890', 'raw' => ['entry_reference' => 'lifecycle-ref']], $overrides);
}

function lifecycleStored(array $overrides = []): Transaction
{
    return Transaction::create(array_replace(['key' => 'tx-'.Str::uuid(), 'source_hash' => hash('sha256', Str::uuid()), 'source_type' => 'api', 'date' => '2026-10-20', 'amount' => -150, 'description' => 'Insurance', 'account_iban' => 'NL00TEST1234567890', 'type' => 'expense', 'bank_payload' => ['entry_reference' => 'lifecycle-ref']], $overrides));
}

test('reservations get rule splits immediately and finalization preserves ID and manual assignments', function () {
    $category = Category::create(['key' => 'life', 'name' => 'Verzekeringen', 'slug' => 'life', 'type' => 'expense']);
    $budget = Budget::create(['key' => 'life-a', 'name' => 'Insurance', 'category_id' => $category->id, 'budget' => 100]);
    $splits = [['budgetItemId' => 'life-a', 'amount' => 100], ['budgetItemId' => 'life-b', 'amount' => 50]];
    ImportRule::create(['key' => 'life-rule', 'name' => 'Insurance', 'type' => 'description', 'match_field' => 'description', 'match_value' => 'Insurance', 'category_id' => $category->id, 'budget_id' => $budget->id, 'is_active' => true, 'allocations' => $splits]);
    $importer = app(EnabledBankingTransactionImporter::class);
    $first = $importer->import([lifecycleBankRow(['status' => 'PDNG'])]);
    $reserved = Transaction::first();
    expect($first['reserved_imported'])->toBe(1)->and($first['issues'])->toBe([])
        ->and($reserved->is_pending)->toBeTrue()->and($reserved->allocations)->toBe($splits);
    $reserved->update(['savings_goal_key' => 'manual-assignment']);
    $result = $importer->import([lifecycleBankRow(['booking_date' => '2026-10-22'])]);
    $booked = Transaction::first();
    expect($result['finalized'])->toBe(1)->and($booked->id)->toBe($reserved->id)
        ->and($booked->key)->toBe($reserved->key)->and($booked->is_pending)->toBeFalse()
        ->and($booked->date->format('Y-m-d'))->toBe('2026-10-22')
        ->and($booked->allocations)->toBe($splits)->and($booked->savings_goal_key)->toBe('manual-assignment');
    $old = Transaction::onlyHistory()->first();
    expect($old->date->format('Y-m-d'))->toBe('2026-10-20')->and($old->is_pending)->toBeTrue()
        ->and($old->superseded_by_key)->toBe($booked->key)->and($old->allocations)->toBe($splits);
    $repeat = $importer->import([lifecycleBankRow(['booking_date' => '2026-10-22']), lifecycleBankRow(['status' => 'PDNG'])]);
    expect($repeat['finalized'])->toBe(0)->and(Transaction::count())->toBe(1)
        ->and(Transaction::onlyHistory()->count())->toBe(1)->and(Transaction::first()->is_pending)->toBeFalse();
});

test('reservations without booking date use bank transaction date without inventing a date', function () {
    $row = lifecycleBankRow(['booking_date' => null, 'transaction_date' => '2026-10-19', 'status' => 'PDNG']);
    $result = app(EnabledBankingTransactionImporter::class)->import([$row]);
    expect($result['imported'])->toBe(1)->and(Transaction::first()->date->format('Y-m-d'))->toBe('2026-10-19');
    $row['transaction_date'] = null;
    expect(app(EnabledBankingTransactionImporter::class)->import([$row])['invalid'])->toBe(1);
});

test('amount changes and conflicting splits never silently replace a reservation', function () {
    $pending = lifecycleStored(['is_pending' => true, 'allocations' => [['budgetItemId' => 'a', 'amount' => 100], ['budgetItemId' => 'b', 'amount' => 50]]]);
    $result = app(EnabledBankingTransactionImporter::class)->import([lifecycleBankRow(['amount' => -151])]);
    expect($result['blocked'])->toBe(1)->and($pending->fresh()->is_pending)->toBeTrue()->and(Transaction::onlyHistory()->count())->toBe(0);
    lifecycleStored(['allocations' => [['budgetItemId' => 'a', 'amount' => 120], ['budgetItemId' => 'b', 'amount' => 30]]]);
    $result = app(EnabledBankingTransactionImporter::class)->import([lifecycleBankRow()]);
    expect($result['conflicts'])->toBe(1)->and(Transaction::count())->toBe(2)->and(Transaction::onlyHistory()->count())->toBe(0);
});

test('a changed bank reference is never guessed from equal date amount and description', function () {
    lifecycleStored(['is_pending' => true]);
    app(EnabledBankingTransactionImporter::class)->import([lifecycleBankRow(['raw' => ['entry_reference' => 'other-ref']])]);
    expect(Transaction::count())->toBe(2)->and(Transaction::booked()->count())->toBe(1)->and(Transaction::onlyHistory()->count())->toBe(0);
});

test('history is excluded from default sums and state; reservations do not mark budgets paid', function () {
    $this->actingAs(User::factory()->create());
    $category = Category::create(['key' => 'life', 'name' => 'Verzekeringen', 'slug' => 'life', 'type' => 'expense']);
    $budget = Budget::create(['key' => 'life-a', 'name' => 'Insurance', 'category_id' => $category->id, 'budget' => 150]);
    $pending = lifecycleStored(['is_pending' => true, 'budget_id' => $budget->id, 'category_id' => $category->id]);
    $history = lifecycleStored(['amount' => -999]);
    (new TransactionLifecycle)->archive($history, $pending, 'confirmed_duplicate');
    $state = app(SparenStateService::class)->build(2026);
    $october = collect($state['monthlyBudgets'])->firstWhere('monthId', 'okt');
    expect($october['items'][0]['paidOrReceived'])->toBe(0.0)->and($october['items'][0]['isPaid'])->toBeFalse()
        ->and(count($state['transactions']))->toBe(1)->and($state['transactions'][0]['status'])->toBe('reserved')
        ->and((float) Transaction::sum('amount'))->toBe(-150.0)->and((float) Transaction::booked()->sum('amount'))->toBe(0.0)
        ->and(Transaction::withHistory()->count())->toBe(2);
});

test('transaction history is authenticated searchable and read only', function () {
    $this->getJson('/api/sparen/transaction-history')->assertUnauthorized();
    $this->actingAs(User::factory()->create());
    $keep = lifecycleStored();
    $old = lifecycleStored(['is_pending' => true]);
    (new TransactionLifecycle)->archive($old, $keep, 'reservation_replaced');
    $this->getJson('/api/sparen/transaction-history?transactionKey='.$keep->key)->assertOk()
        ->assertJsonPath('total', 1)->assertJsonPath('data.0.status', 'history')->assertJsonPath('data.0.supersededBy', $keep->key);
    $this->getJson('/api/sparen/transaction-history?search=unknown')->assertOk()->assertJsonPath('total', 0);
    $this->putJson('/api/sparen/transactions/'.$old->key, ['amount' => 0, 'isPending' => false, 'source' => 'Handmatig'])->assertStatus(409);
    expect(Transaction::onlyHistory()->first()->amount)->toBe('-150.00');
});

test('a stale browser cannot reverse bank finalization while saving links', function () {
    $this->actingAs(User::factory()->create());
    $booked = lifecycleStored();
    $this->putJson('/api/sparen/transactions/'.$booked->key, ['date' => '2026-10-18', 'amount' => -999, 'isPending' => true, 'assignedSavingsGoalId' => 'chosen'])->assertOk();
    expect($booked->fresh()->is_pending)->toBeFalse()->and($booked->fresh()->amount)->toBe('-150.00')
        ->and($booked->fresh()->date->format('Y-m-d'))->toBe('2026-10-20')->and($booked->fresh()->savings_goal_key)->toBe('chosen');
});

test('stored reconciliation is conservative idempotent and keeps all original rows', function () {
    $keep = lifecycleStored();
    lifecycleStored(['is_pending' => true, 'date' => '2026-10-18', 'savings_goal_key' => 'chosen']);
    lifecycleStored();
    $service = new TransactionLifecycle;
    expect($service->reconcileStored()['eligible'])->toBe(2)->and(Transaction::count())->toBe(3);
    expect($service->reconcileStored(true)['archived'])->toBe(2)->and(Transaction::count())->toBe(1)
        ->and(Transaction::withHistory()->count())->toBe(3)->and($keep->fresh()->savings_goal_key)->toBe('chosen');
    expect($service->reconcileStored(true)['archived'])->toBe(0);
});

test('an old session report no longer asks to reconcile already archived reservations', function () {
    $keep = lifecycleStored();
    $old = lifecycleStored(['is_pending' => true, 'date' => '2026-10-18']);
    (new TransactionLifecycle)->archive($old, $keep, 'reservation_replaced');
    $issue = ['code' => 'pending_booking', 'date' => '2026-10-20', 'amount' => -150, 'reference' => 'lifecycle-ref', 'account' => $keep->account_iban, 'existing' => [['id' => $old->key], ['id' => $keep->key]]];
    $result = app(BankSyncReport::class)->normalize(['pending' => 1, 'pending_booking' => 1, 'issues' => [$issue]]);
    expect($result['issues'])->toBe([])->and($result['pending'])->toBe(0)->and($result['pending_booking'])->toBe(0);
    $issue['amount'] = -151;
    expect(app(BankSyncReport::class)->normalize(['issues' => [$issue]])['issues'])->toHaveCount(1);
});
