<?php

use App\Models\EnableBankingSession;
use App\Models\User;
use App\Services\EnableBanking;

test('sparen-state gebruikt de database-sessie in plaats van de laravel-sessie', function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-08-26 10:00:00', 'Europe/Amsterdam'));

    EnableBankingSession::query()->create([
        'session_id' => 'sess-db',
        'status' => 'authorized',
        'valid_until' => \Illuminate\Support\Carbon::parse('2026-11-22 15:00:00', 'Europe/Amsterdam'),
        'aspsp_name' => 'ING',
        'aspsp_country' => 'NL',
        'accounts' => [],
    ]);

    $this->getJson('/api/sparen/state')
        ->assertOk()
        ->assertJsonPath('enableBankingConnected', true)
        ->assertJsonPath('enableBankingConsent.expired', false)
        ->assertJsonPath('enableBankingConsent.daysRemaining', 88)
        ->assertJsonPath('enableBankingConsent.aspspName', 'ING');
});

test('verlopen consent telt niet meer als actieve koppeling', function () {
    $this->actingAs(User::factory()->create());

    EnableBankingSession::query()->create([
        'session_id' => 'sess-old',
        'status' => 'authorized',
        'valid_until' => now()->subDay(),
        'aspsp_name' => 'ING',
        'aspsp_country' => 'NL',
        'accounts' => [],
    ]);

    $this->getJson('/api/sparen/state')
        ->assertOk()
        ->assertJsonPath('enableBankingConnected', false)
        ->assertJsonPath('enableBankingConsent.expired', true);
});

test('sync gebruikt de database-sessie ook zonder laravel-sessie', function () {
    $this->actingAs(User::factory()->create());

    EnableBankingSession::query()->create([
        'session_id' => 'sess-db',
        'status' => 'authorized',
        'valid_until' => now()->addDays(180),
        'aspsp_name' => 'ING',
        'aspsp_country' => 'NL',
        'accounts' => [
            [
                'uid' => 'acc-1',
                'name' => 'Betaalrekening',
                'currency' => 'EUR',
                'account_id' => ['iban' => 'NL00INGB0000000000'],
            ],
        ],
    ]);

    $this->mock(EnableBanking::class, function ($mock) {
        $mock->shouldReceive('getSessionData')->once()->andReturn([
            'session_id' => 'sess-db',
            'accounts' => [
                [
                    'uid' => 'acc-1',
                    'name' => 'Betaalrekening',
                    'currency' => 'EUR',
                    'account_id' => ['iban' => 'NL00INGB0000000000'],
                ],
            ],
            'access' => ['valid_until' => now()->addDays(180)->toIso8601String()],
            'aspsp' => ['name' => 'ING', 'country' => 'NL'],
        ]);
        $mock->shouldReceive('getBalances')->once()->andReturn([
            'balances' => [['balance_amount' => ['amount' => 12.34]]],
        ]);
        $mock->shouldReceive('getAllTransactions')->once()->andReturn([]);
    });

    $this->postJson('/api/sparen/sync-bank')
        ->assertOk()
        ->assertJsonPath('needsConnect', false);
});

test('sync vraagt opnieuw om koppeling als er geen sessie is', function () {
    $this->actingAs(User::factory()->create());

    $this->mock(EnableBanking::class, function ($mock) {
        $mock->shouldReceive('initAuth')->once()->andReturn([
            'url' => 'https://bank.test/auth',
            'generated_state' => 'state-1',
        ]);
    });

    $this->postJson('/api/sparen/sync-bank')
        ->assertStatus(409)
        ->assertJsonPath('needsConnect', true)
        ->assertJsonPath('url', 'https://bank.test/auth');
});

test('een laravel-sessie wordt overgenomen in de database', function () {
    $this->actingAs(User::factory()->create());

    $this->withSession([
        'eb_session_id' => 'from-http',
        'eb_cached_accounts' => [
            ['uid' => 'acc-1', 'name' => 'Betaalrekening'],
        ],
    ])->getJson('/api/sparen/state')
        ->assertOk()
        ->assertJsonPath('enableBankingConnected', true);

    expect(EnableBankingSession::query()->where('session_id', 'from-http')->exists())->toBeTrue();
});
