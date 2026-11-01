<?php

use App\Models\BankAccount;
use App\Models\BankImportReview;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BankImportReviewService;
use App\Services\EnabledBankingTransactionImporter;
use Illuminate\Support\Str;

function blockedBankTransaction(array $values = []): Transaction
{
    return Transaction::create(array_replace([
        'key' => 'tx-'.Str::uuid(), 'source_hash' => hash('sha256', Str::uuid()), 'source_type' => 'api',
        'date' => '2026-10-20', 'amount' => -150, 'description' => 'Oude bankregistratie', 'type' => 'expense',
        'account_iban' => 'NL00TEST1234567890', 'bank_payload' => ['entry_reference' => 'blocked-ref'],
    ], $values))->fresh();
}

function blockedBankIncoming(array $values = []): array
{
    return array_replace([
        'booking_date' => '2026-10-22', 'amount' => -180.01, 'description' => 'Nieuwe bankboeking',
        'merchant' => 'Verzekeraar', 'account_iban' => 'NL00TEST1234567890', 'status' => 'BOOK',
        'raw' => ['entry_reference' => 'blocked-ref'],
    ], $values);
}

function blockedBankProposal(array $row = []): BankImportReview
{
    app(EnabledBankingTransactionImporter::class)->import([blockedBankIncoming($row)]);

    return BankImportReview::query()->where('status', 'open')->latest('id')->firstOrFail();
}

function blockedBankAcceptance(BankImportReview $review, array $values = []): array
{
    $details = app(BankImportReviewService::class)->details($review->fresh());

    return array_replace([
        'revision' => $details['revision'], 'keeperKey' => $details['existing'][0]['id'], 'linkSource' => 'merge',
        'existingRevisions' => collect($details['existing'])->mapWithKeys(fn ($tx) => [$tx['id'] => $tx['revision']])->all(),
        'scaleSplit' => false,
    ], $values);
}

test('bank review routes require authentication and do not accept fabricated bank data', function () {
    $this->getJson('/api/sparen/bank-reviews')->assertUnauthorized();
    $this->getJson('/api/sparen/bank-reviews/missing')->assertUnauthorized();
    $this->postJson('/api/sparen/bank-reviews/missing/accept')->assertUnauthorized();
    $this->actingAs(User::factory()->create());
    $this->postJson('/api/sparen/bank-reviews/missing/accept', ['amount' => 999])->assertUnprocessable();
    expect(Transaction::count())->toBe(0);
});

test('valid blocked final bookings are persisted once with a review link and never count as money', function () {
    $old = blockedBankTransaction(['is_pending' => true]);
    $row = blockedBankIncoming();
    $importer = app(EnabledBankingTransactionImporter::class);
    $first = $importer->import([$row]);
    $second = $importer->import([array_reverse($row, true)]);
    expect($first['blocked'])->toBe(1)->and($first['issues'][0]['reviewKey'])->toBe($second['issues'][0]['reviewKey'])
        ->and(BankImportReview::count())->toBe(1)->and(Transaction::count())->toBe(1)
        ->and(Transaction::onlyHistory()->count())->toBe(0)->and($old->fresh()->amount)->toBe('-150.00');
    $this->actingAs(User::factory()->create());
    $this->getJson('/api/sparen/bank-reviews')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.amount', -180.01);
    $this->getJson('/api/sparen/bank-reviews/'.$first['issues'][0]['reviewKey'])->assertOk()
        ->assertJsonPath('existing.0.id', $old->key)->assertJsonPath('existing.0.isPending', true);
});

test('changed provisional amount can be accepted with explicit split scaling while preserving its ID and full history', function () {
    $this->actingAs(User::factory()->create());
    $split = [['budgetItemId' => 'a', 'amount' => 100], ['budgetItemId' => 'b', 'amount' => 50]];
    $old = blockedBankTransaction(['is_pending' => true, 'allocations' => $split, 'savings_goal_key' => 'manual-pot']);
    $review = blockedBankProposal();
    $payload = blockedBankAcceptance($review);
    $this->postJson('/api/sparen/bank-reviews/'.$review->key.'/accept', $payload)->assertUnprocessable();
    expect(Transaction::onlyHistory()->count())->toBe(0)->and($old->fresh()->is_pending)->toBeTrue();
    $this->postJson('/api/sparen/bank-reviews/'.$review->key.'/accept', [...$payload, 'scaleSplit' => true, 'amount' => -999, 'date' => '2030-01-01'])
        ->assertOk()->assertJsonPath('booked.id', $old->key)->assertJsonPath('booked.amount', -180.01)
        ->assertJsonPath('booked.date', '2026-10-22')->assertJsonPath('reviewCount', 0);
    expect($old->fresh()->is_pending)->toBeFalse()->and($old->fresh()->savings_goal_key)->toBe('manual-pot')
        ->and($old->fresh()->allocations)->toEqual([['budgetItemId' => 'a', 'amount' => 120.01], ['budgetItemId' => 'b', 'amount' => 60]])
        ->and($old->fresh()->bank_payload)->toBe(BankImportReview::first()->incoming_payload)
        ->and(Transaction::onlyHistory()->count())->toBe(1)->and(Transaction::count())->toBe(1);
    $history = Transaction::onlyHistory()->first();
    expect($history->is_pending)->toBeTrue()->and($history->amount)->toBe('-150.00')
        ->and($history->date->format('Y-m-d'))->toBe('2026-10-20')->and($history->allocations)->toEqual($split)
        ->and($history->superseded_by_key)->toBe($old->key);
});

test('an already booked bank correction updates only approved bank fields and preserves budgets and live balances', function () {
    $this->actingAs(User::factory()->create());
    $category = Category::create(['key' => 'review', 'slug' => 'review', 'name' => 'Verzekeringen', 'type' => 'expense']);
    $budget = Budget::create(['key' => 'review', 'name' => 'Verzekering', 'category_id' => $category->id, 'budget' => 150]);
    $account = BankAccount::create(['key' => 'test-account', 'name' => 'Test', 'type' => 'checking', 'balance' => 1234.56, 'available_balance' => 1200]);
    $old = blockedBankTransaction(['budget_id' => $budget->id, 'category_id' => $category->id]);
    $hash = $old->source_hash;
    $review = blockedBankProposal();
    $this->postJson('/api/sparen/bank-reviews/'.$review->key.'/accept', blockedBankAcceptance($review))->assertOk();
    expect($old->fresh()->budget_id)->toBe($budget->id)->and($old->fresh()->source_hash)->toBe($hash)
        ->and((float) $budget->fresh()->budget)->toBe(150.0)->and((float) $account->fresh()->balance)->toBe(1234.56)
        ->and((float) $account->fresh()->available_balance)->toBe(1200.0)
        ->and((float) Transaction::booked()->sum('amount'))->toBe(-180.01)
        ->and(Transaction::onlyHistory()->first()->is_pending)->toBeFalse();
});

test('changed bank version and concurrent link changes reject stale approvals without partial history', function () {
    $this->actingAs(User::factory()->create());
    $old = blockedBankTransaction();
    $review = blockedBankProposal();
    $payload = blockedBankAcceptance($review);
    $old->update(['savings_goal_key' => 'changed']);
    $this->postJson('/api/sparen/bank-reviews/'.$review->key.'/accept', $payload)->assertStatus(409);
    $payload = blockedBankAcceptance($review);
    app(EnabledBankingTransactionImporter::class)->import([blockedBankIncoming(['description' => 'Nieuwe omschrijving'])]);
    $this->postJson('/api/sparen/bank-reviews/'.$review->key.'/accept', $payload)->assertStatus(409);
    expect(Transaction::onlyHistory()->count())->toBe(0)->and($old->fresh()->amount)->toBe('-150.00');
});

test('new copies require a refreshed approval and foreign keeper and link sources cannot be selected', function () {
    $this->actingAs(User::factory()->create());
    $old = blockedBankTransaction();
    $review = blockedBankProposal();
    $payload = blockedBankAcceptance($review);
    blockedBankTransaction();
    $this->postJson('/api/sparen/bank-reviews/'.$review->key.'/accept', $payload)->assertStatus(409);
    $payload = blockedBankAcceptance($review);
    $this->postJson('/api/sparen/bank-reviews/'.$review->key.'/accept', [...$payload, 'keeperKey' => 'unknown'])->assertUnprocessable();
    $this->postJson('/api/sparen/bank-reviews/'.$review->key.'/accept', [...$payload, 'linkSource' => 'unknown'])->assertUnprocessable();
    expect(Transaction::onlyHistory()->count())->toBe(0);
});

test('conflicting copies require a link source and all displaced bank registrations remain in history', function () {
    $this->actingAs(User::factory()->create());
    $pending = blockedBankTransaction(['is_pending' => true, 'savings_goal_key' => 'pending-pot']);
    $booked = blockedBankTransaction(['savings_goal_key' => 'booked-pot']);
    $review = blockedBankProposal();
    $payload = blockedBankAcceptance($review, ['keeperKey' => $booked->key]);
    $this->postJson('/api/sparen/bank-reviews/'.$review->key.'/accept', $payload)->assertUnprocessable();
    $this->postJson('/api/sparen/bank-reviews/'.$review->key.'/accept', [...$payload, 'linkSource' => $pending->key])->assertOk();
    expect(Transaction::count())->toBe(1)->and(Transaction::withHistory()->count())->toBe(3)
        ->and($booked->fresh()->savings_goal_key)->toBe('pending-pot')
        ->and(Transaction::onlyHistory()->where('archive_reason', 'bank_booking_before_acceptance')->first()->savings_goal_key)->toBe('booked-pot')
        ->and(Transaction::onlyHistory()->where('key', $pending->key)->first()->superseded_by_key)->toBe($booked->key);
});

test('accepting a proposal closes the sync issue and repeated deliveries cannot double-book or append history', function () {
    $this->actingAs(User::factory()->create());
    $old = blockedBankTransaction(['is_pending' => true]);
    $importer = app(EnabledBankingTransactionImporter::class);
    $report = $importer->import([blockedBankIncoming()]);
    $review = BankImportReview::first();
    $payload = blockedBankAcceptance($review);
    $this->withSession(['sparen.sync_report' => $report])->postJson('/api/sparen/bank-reviews/'.$review->key.'/accept', $payload)
        ->assertOk()->assertJsonPath('report.blocked', 0)->assertJsonPath('report.issues', []);
    $this->postJson('/api/sparen/bank-reviews/'.$review->key.'/accept', $payload)->assertStatus(409);
    $repeat = $importer->import([blockedBankIncoming()]);
    expect($repeat['duplicates'])->toBe(1)->and($repeat['blocked'])->toBe(0)
        ->and(Transaction::count())->toBe(1)->and(Transaction::onlyHistory()->count())->toBe(1)
        ->and(BankImportReview::where('status', 'open')->count())->toBe(0);
    $importer->import([blockedBankIncoming(['amount' => -200])]);
    expect(BankImportReview::count())->toBe(2)->and($review->fresh()->incoming_payload['amount'])->toBe(-180.01)
        ->and($old->fresh()->amount)->toBe('-180.01');
});

test('a later unambiguous bank confirmation makes an obsolete proposal read only without changing money', function () {
    $this->actingAs(User::factory()->create());
    $old = blockedBankTransaction();
    $review = blockedBankProposal();
    $payload = blockedBankAcceptance($review);
    app(EnabledBankingTransactionImporter::class)->import([blockedBankIncoming(['amount' => -150, 'booking_date' => '2026-10-20'])]);
    expect($review->fresh()->status)->toBe('superseded')->and($old->fresh()->amount)->toBe('-150.00')
        ->and(Transaction::onlyHistory()->count())->toBe(0);
    $this->postJson('/api/sparen/bank-reviews/'.$review->key.'/accept', $payload)->assertStatus(409);
});

test('contradictory bank versions in one delivery never auto-dismiss the blocked version', function () {
    blockedBankTransaction();
    $review = blockedBankProposal();
    $result = app(EnabledBankingTransactionImporter::class)->import([
        blockedBankIncoming(['amount' => -150, 'booking_date' => '2026-10-20']), blockedBankIncoming(),
    ]);
    expect($result['blocked'])->toBe(1)->and($review->fresh()->status)->toBe('open');
});

test('invalid identities and provisional conflicts cannot become approvable bank bookings', function () {
    blockedBankTransaction();
    app(EnabledBankingTransactionImporter::class)->import([
        blockedBankIncoming(['raw' => []]), blockedBankIncoming(['booking_date' => null]),
        blockedBankIncoming(['status' => 'PDNG']), blockedBankIncoming(['amount' => 'invalid']),
    ]);
    expect(BankImportReview::count())->toBe(0)->and(Transaction::count())->toBe(1);
});

test('reversed payment direction requires different bank data and cannot silently reuse old expense links', function () {
    $this->actingAs(User::factory()->create());
    $old = blockedBankTransaction();
    $review = blockedBankProposal(['amount' => 180]);
    $this->postJson('/api/sparen/bank-reviews/'.$review->key.'/accept', blockedBankAcceptance($review))->assertUnprocessable();
    expect($old->fresh()->amount)->toBe('-150.00')->and(Transaction::onlyHistory()->count())->toBe(0);
});

test('legacy transaction IDs and multiple bank proposals remain reviewable', function () {
    $this->actingAs(User::factory()->create());
    $old = blockedBankTransaction(['key' => null]);
    $review = blockedBankProposal();
    blockedBankProposal(['amount' => -190]);
    $this->getJson('/api/sparen/bank-reviews/'.$review->key)->assertOk()->assertJsonPath('otherVersions', 1)
        ->assertJsonPath('existing.0.id', 'tx-'.$old->id);
    $this->postJson('/api/sparen/bank-reviews/'.$review->key.'/accept', blockedBankAcceptance($review))->assertOk();
    expect(BankImportReview::where('status', 'open')->count())->toBe(0)
        ->and(BankImportReview::where('status', 'superseded')->count())->toBe(1)
        ->and($old->fresh()->amount)->toBe('-180.01');
});

test('a competing approval cannot reuse a superseded bank version', function () {
    $this->actingAs(User::factory()->create());
    $old = blockedBankTransaction();
    $first = blockedBankProposal();
    $second = blockedBankProposal(['amount' => -200]);
    $firstPayload = blockedBankAcceptance($first);
    $secondPayload = blockedBankAcceptance($second);
    $this->postJson('/api/sparen/bank-reviews/'.$second->key.'/accept', $secondPayload)->assertOk();
    $this->postJson('/api/sparen/bank-reviews/'.$first->key.'/accept', $firstPayload)->assertStatus(409);
    expect($old->fresh()->amount)->toBe('-200.00')->and(Transaction::onlyHistory()->count())->toBe(1);
});

test('tiny definitive amounts cannot create zero or negative split parts', function () {
    $this->actingAs(User::factory()->create());
    $split = [['budgetItemId' => 'a', 'amount' => 100], ['budgetItemId' => 'b', 'amount' => 50]];
    $old = blockedBankTransaction(['is_pending' => true, 'allocations' => $split]);
    $review = blockedBankProposal(['amount' => -0.01]);
    $this->postJson('/api/sparen/bank-reviews/'.$review->key.'/accept', blockedBankAcceptance($review, ['scaleSplit' => true]))->assertUnprocessable();
    expect($old->fresh()->amount)->toBe('-150.00')->and(Transaction::onlyHistory()->count())->toBe(0)
        ->and($review->fresh()->status)->toBe('open');
});
