<?php

use App\Models\Transaction;
use App\Models\User;
use App\Services\EnabledBankingTransactionImporter;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

function reviewPayment(array $values = []): Transaction
{
    return Transaction::create(array_replace([
        'key' => 'tx-'.Str::uuid(), 'source_hash' => hash('sha256', Str::uuid()),
        'source_type' => 'api', 'date' => '2026-10-20', 'description' => 'Insurance',
        'amount' => -150, 'type' => 'expense', 'account_iban' => 'NL00TEST1234567890',
        'bank_payload' => ['entry_reference' => 'review-'.Str::uuid()],
    ], $values))->fresh();
}

function reviewResolution(Transaction $pending, ?Transaction $booked = null, array $values = []): array
{
    return array_replace([
        'action' => $booked ? 'match' : 'expire', 'revision' => $pending->fresh()->revision(),
        ...($booked ? ['bookedKey' => $booked->key, 'bookedRevision' => $booked->fresh()->revision(), 'linkChoice' => 'merge'] : []),
    ], $values);
}

test('reservation review endpoints require authentication and an active bank reservation', function () {
    $this->getJson('/api/sparen/reservations/missing/review')->assertUnauthorized();
    $this->postJson('/api/sparen/reservations/missing/resolve')->assertUnauthorized();
    $this->actingAs(User::factory()->create());
    $booked = reviewPayment();
    $this->getJson('/api/sparen/reservations/'.$booked->key.'/review')->assertStatus(409);
    $manual = reviewPayment(['source_type' => 'manual', 'is_pending' => true]);
    $this->postJson('/api/sparen/reservations/'.$manual->key.'/resolve', reviewResolution($manual))->assertStatus(409);
});

test('review offers only active booked same-account same-direction candidates and supports wider search', function () {
    $this->actingAs(User::factory()->create());
    $pending = reviewPayment(['is_pending' => true]);
    $candidate = reviewPayment(['description' => 'Recent final']);
    $older = reviewPayment(['date' => '2026-01-01', 'description' => 'Older final']);
    reviewPayment(['amount' => 150]);
    reviewPayment(['account_iban' => 'NL00TEST0000000001']);
    reviewPayment(['is_pending' => true]);
    reviewPayment(['archived_at' => now(), 'archive_reason' => 'confirmed_duplicate']);
    $this->getJson('/api/sparen/reservations/'.$pending->key.'/review')->assertOk()
        ->assertJsonCount(1, 'candidates')->assertJsonPath('candidates.0.id', $candidate->key)
        ->assertJsonPath('reservation.revision', $pending->revision());
    $this->getJson('/api/sparen/reservations/'.$pending->key.'/review?search=Older')->assertOk()
        ->assertJsonCount(1, 'candidates')->assertJsonPath('candidates.0.id', $older->key);
});

test('explicit matching preserves definitive bank data transfers missing links and retains both originals', function () {
    $this->actingAs(User::factory()->create());
    $pending = reviewPayment(['is_pending' => true, 'savings_goal_key' => 'manual-pot', 'date' => '2026-10-18']);
    $booked = reviewPayment(['date' => '2026-10-22']);
    $before = Arr::only($booked->getRawOriginal(), ['date', 'amount', 'description', 'bank_payload', 'account_iban', 'source_hash']);
    $this->postJson('/api/sparen/reservations/'.$pending->key.'/resolve', reviewResolution($pending, $booked))
        ->assertOk()->assertJsonPath('reservationKey', $pending->key)->assertJsonPath('booked.id', $booked->key);
    expect(Transaction::count())->toBe(1)->and(Transaction::withHistory()->count())->toBe(3)
        ->and(Arr::only($booked->fresh()->getRawOriginal(), array_keys($before)))->toBe($before)
        ->and($booked->fresh()->savings_goal_key)->toBe('manual-pot')
        ->and(Transaction::onlyHistory()->where('key', $pending->key)->first()->archive_reason)->toBe('reservation_matched')
        ->and(Transaction::onlyHistory()->where('archive_reason', 'links_before_reservation_match')->count())->toBe(1);
});

test('stale reservation and definitive revisions reject without changing either row', function () {
    $this->actingAs(User::factory()->create());
    $pending = reviewPayment(['is_pending' => true]);
    $booked = reviewPayment();
    $payload = reviewResolution($pending, $booked);
    $pending->update(['savings_goal_key' => 'changed']);
    $this->postJson('/api/sparen/reservations/'.$pending->key.'/resolve', $payload)->assertStatus(409);
    $payload = reviewResolution($pending, $booked);
    $booked->update(['description' => 'Changed by another sync']);
    $this->postJson('/api/sparen/reservations/'.$pending->key.'/resolve', $payload)->assertStatus(409);
    expect(Transaction::count())->toBe(2)->and(Transaction::onlyHistory()->count())->toBe(0);
});

test('wrong own account direction and an unbooked target cannot be matched', function () {
    $this->actingAs(User::factory()->create());
    $pending = reviewPayment(['is_pending' => true]);
    foreach ([['account_iban' => 'NL00TEST0000000001'], ['amount' => 150], ['is_pending' => true]] as $values) {
        $other = reviewPayment($values);
        $this->postJson('/api/sparen/reservations/'.$pending->key.'/resolve', reviewResolution($pending, $other))->assertUnprocessable();
    }
    expect(Transaction::onlyHistory()->count())->toBe(0);
});

test('conflicting assignments require an explicit choice and retain the displaced choice in history', function () {
    $this->actingAs(User::factory()->create());
    $pending = reviewPayment(['is_pending' => true, 'savings_goal_key' => 'reservation-pot']);
    $booked = reviewPayment(['savings_goal_key' => 'booked-pot']);
    $payload = reviewResolution($pending, $booked);
    $this->postJson('/api/sparen/reservations/'.$pending->key.'/resolve', $payload)->assertUnprocessable();
    expect(Transaction::onlyHistory()->count())->toBe(0);
    $this->postJson('/api/sparen/reservations/'.$pending->key.'/resolve', [...$payload, 'linkChoice' => 'reservation'])->assertOk();
    expect($booked->fresh()->savings_goal_key)->toBe('reservation-pot')
        ->and(Transaction::onlyHistory()->where('archive_reason', 'links_before_reservation_match')->first()->savings_goal_key)->toBe('booked-pot');
});

test('a changed final amount needs explicit split rescaling in cents', function () {
    $this->actingAs(User::factory()->create());
    $split = [['budgetItemId' => 'a', 'amount' => 100], ['budgetItemId' => 'b', 'amount' => 50]];
    $pending = reviewPayment(['is_pending' => true, 'allocations' => $split]);
    $booked = reviewPayment(['amount' => -180.01]);
    $payload = reviewResolution($pending, $booked, ['linkChoice' => 'reservation']);
    $this->postJson('/api/sparen/reservations/'.$pending->key.'/resolve', $payload)->assertUnprocessable();
    expect(Transaction::onlyHistory()->count())->toBe(0)->and($booked->fresh()->allocations)->toBeNull();
    $this->postJson('/api/sparen/reservations/'.$pending->key.'/resolve', [...$payload, 'scaleSplit' => true])->assertOk();
    expect($booked->fresh()->amount)->toBe('-180.01')
        ->and($booked->fresh()->allocations)->toEqual([['budgetItemId' => 'a', 'amount' => 120.01], ['budgetItemId' => 'b', 'amount' => 60]])
        ->and(Transaction::onlyHistory()->where('key', $pending->key)->first()->allocations)->toEqual($split);
});

test('keeping definitive links leaves the reservation split unchanged in history without rescaling', function () {
    $this->actingAs(User::factory()->create());
    $split = [['budgetItemId' => 'a', 'amount' => 100], ['budgetItemId' => 'b', 'amount' => 50]];
    $pending = reviewPayment(['is_pending' => true, 'allocations' => $split]);
    $booked = reviewPayment(['amount' => -180]);
    $this->postJson('/api/sparen/reservations/'.$pending->key.'/resolve', reviewResolution($pending, $booked, ['linkChoice' => 'booked']))->assertOk();
    expect($booked->fresh()->allocations)->toBeNull()->and(Transaction::onlyHistory()->where('key', $pending->key)->first()->allocations)->toEqual($split);
});

test('expiring a reservation keeps history and suppresses repeat provisional deliveries but never final bookings', function () {
    $this->actingAs(User::factory()->create());
    $pending = reviewPayment(['is_pending' => true, 'bank_payload' => ['entry_reference' => 'expired-ref']]);
    $this->postJson('/api/sparen/reservations/'.$pending->key.'/resolve', reviewResolution($pending))->assertOk()->assertJsonPath('booked', null);
    expect(Transaction::count())->toBe(0)->and(Transaction::onlyHistory()->count())->toBe(1);
    $row = ['booking_date' => '2026-10-20', 'amount' => -150, 'description' => 'Insurance', 'account_iban' => $pending->account_iban, 'raw' => ['entry_reference' => 'expired-ref'], 'status' => 'PDNG'];
    $importer = app(EnabledBankingTransactionImporter::class);
    expect($importer->import([$row])['ignored_reservations'])->toBe(1)->and(Transaction::count())->toBe(0);
    expect($importer->import([[...$row, 'status' => 'BOOK']])['imported'])->toBe(1)
        ->and(Transaction::count())->toBe(1)->and(Transaction::first()->is_pending)->toBeFalse()
        ->and(Transaction::onlyHistory()->count())->toBe(1);
});

test('confirmed changed-reference matches prevent duplicate reimport of the old reservation', function () {
    $this->actingAs(User::factory()->create());
    $pending = reviewPayment(['is_pending' => true, 'bank_payload' => ['entry_reference' => 'old-ref']]);
    $booked = reviewPayment(['bank_payload' => ['entry_reference' => 'new-ref']]);
    $this->postJson('/api/sparen/reservations/'.$pending->key.'/resolve', reviewResolution($pending, $booked))->assertOk();
    $row = ['booking_date' => '2026-10-20', 'amount' => -150, 'description' => 'Insurance', 'account_iban' => $pending->account_iban, 'raw' => ['entry_reference' => 'old-ref'], 'status' => 'PDNG'];
    $importer = app(EnabledBankingTransactionImporter::class);
    expect($importer->import([$row])['ignored_reservations'])->toBe(1);
    expect($importer->import([[...$row, 'status' => 'BOOK']])['duplicates'])->toBe(1);
    expect($importer->import([[...$row, 'status' => 'BOOK', 'amount' => -151]])['blocked'])->toBe(1);
    expect(Transaction::count())->toBe(1)->and(Transaction::onlyHistory()->count())->toBe(2)
        ->and($booked->fresh()->amount)->toBe('-150.00');
});

test('legacy reservation keys can be reviewed and repeated resolutions are rejected', function () {
    $this->actingAs(User::factory()->create());
    $pending = reviewPayment(['key' => null, 'is_pending' => true]);
    $key = 'tx-'.$pending->id;
    $this->getJson('/api/sparen/reservations/'.$key.'/review')->assertOk()->assertJsonPath('reservation.id', $key);
    $payload = reviewResolution($pending);
    $this->postJson('/api/sparen/reservations/'.$key.'/resolve', $payload)->assertOk();
    $this->postJson('/api/sparen/reservations/'.$key.'/resolve', $payload)->assertStatus(409);
    expect(Transaction::onlyHistory()->count())->toBe(1);
});

test('reservation review cannot save a rescaled split with zero or negative parts', function () {
    $this->actingAs(User::factory()->create());
    $pending = reviewPayment(['is_pending' => true, 'allocations' => [['budgetItemId' => 'a', 'amount' => 100], ['budgetItemId' => 'b', 'amount' => 50]]]);
    $booked = reviewPayment(['amount' => -0.01]);
    $this->postJson('/api/sparen/reservations/'.$pending->key.'/resolve', reviewResolution($pending, $booked, ['linkChoice' => 'reservation', 'scaleSplit' => true]))->assertUnprocessable();
    expect(Transaction::onlyHistory()->count())->toBe(0)->and($booked->fresh()->amount)->toBe('-0.01');
});
