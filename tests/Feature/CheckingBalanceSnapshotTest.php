<?php

use App\Models\BankAccount;
use App\Models\CheckingPeriodSnapshot;
use App\Models\Transaction;
use App\Services\CheckingBalanceSnapshotService;
use App\Services\HistoricalSavingsGoalOverrides;
use App\Support\IngSavingsTransfer;
use Carbon\CarbonImmutable;

it('legt een maandeinde-saldo vast en overschrijft het niet als het live saldo later verandert', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-03 12:00:00', 'Europe/Amsterdam'));

    $checking = BankAccount::query()->create([
        'key' => 'acc-checking-1',
        'name' => 'Betaalrekening',
        'iban' => 'NL00INGB0000000000',
        'type' => 'checking',
        'balance' => 1000,
        'available_balance' => 1000,
        'currency' => 'EUR',
        'status' => 'connected',
    ]);

    Transaction::query()->create([
        'key' => 'tx-first',
        'description' => 'Eerste mutatie',
        'amount' => 500,
        'date' => '2026-05-20',
        'type' => 'income',
        'is_pending' => false,
    ]);
    Transaction::query()->create([
        'key' => 'tx-after-jul',
        'description' => 'Na jul-periode',
        'amount' => -100,
        'date' => '2026-08-20',
        'type' => 'expense',
        'is_pending' => false,
    ]);

    $service = app(CheckingBalanceSnapshotService::class);
    $first = $service->syncYear(2026, $checking, Transaction::query()->get());

    expect($first['jan']['captured'])->toBeFalse()
        ->and($first['jan']['balance'])->toBeNull()
        ->and($first['apr']['captured'])->toBeFalse()
        ->and($first['jul']['captured'])->toBeTrue()
        ->and($first['jul']['balance'])->toBe(1100.0)
        ->and($first['aug']['captured'])->toBeFalse()
        ->and($first['aug']['balance'])->toBe(1000.0);

    $checking->update(['balance' => 2000]);
    $second = $service->syncYear(2026, $checking->fresh(), Transaction::query()->get());

    expect($second['jul']['balance'])->toBe(1100.0)
        ->and((float) CheckingPeriodSnapshot::query()->where('month_id', 'jul')->value('balance'))->toBe(1100.0);
});

it('herkent de drie historische L-stortingen als override-kandidaat, zonder C-potjes', function () {
    $overrides = app(HistoricalSavingsGoalOverrides::class);

    $candidate = new Transaction([
        'description' => 'Naar Oranje spaarrekening L13628386',
        'amount' => -100,
        'date' => '2026-06-24',
    ]);
    $sameDayOnderhoud = new Transaction([
        'description' => 'Naar Oranje spaarrekening L13628386',
        'amount' => -75,
        'date' => '2026-06-24',
    ]);
    $gluedPot = new Transaction([
        'description' => 'Naar Oranje spaarrekeningC13134173',
        'amount' => -100,
        'date' => '2026-06-24',
    ]);

    expect($overrides->isCandidate($candidate))->toBeTrue()
        ->and($overrides->isCandidate($sameDayOnderhoud))->toBeFalse()
        ->and($overrides->isCandidate($gluedPot))->toBeFalse()
        ->and(IngSavingsTransfer::parseDestination($candidate->description)['isSpaarpot'])->toBeFalse();
});
