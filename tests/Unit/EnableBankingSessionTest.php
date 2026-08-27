<?php

use App\Models\EnableBankingSession;
use Illuminate\Support\Carbon;

uses(Tests\TestCase::class);

it('leest valid_until uit access.valid_until', function () {
    $until = EnableBankingSession::validUntilFromPayload([
        'access' => ['valid_until' => '2026-11-22T12:00:00Z'],
    ]);

    expect($until)->not->toBeNull()
        ->and($until->utc()->toDateString())->toBe('2026-11-22');
});

it('is bruikbaar zolang de consent in de toekomst ligt', function () {
    $this->travelTo(Carbon::parse('2026-08-26 10:00:00', 'Europe/Amsterdam'));

    $session = new EnableBankingSession([
        'status' => 'authorized',
        'valid_until' => Carbon::parse('2026-11-22 15:00:00', 'Europe/Amsterdam'),
    ]);

    expect($session->isUsable())->toBeTrue()
        ->and($session->daysRemaining())->toBe(88);
});

it('is niet bruikbaar na het verlopen van de consent', function () {
    $session = new EnableBankingSession([
        'status' => 'authorized',
        'valid_until' => now()->subHour(),
    ]);

    expect($session->isUsable())->toBeFalse()
        ->and($session->daysRemaining())->toBe(0)
        ->and($session->toConsentPayload()['expired'])->toBeTrue();
});

it('telt resterende dagen in Amsterdamse kalenderdagen', function () {
    $this->travelTo(Carbon::parse('2026-08-26 22:30:00', 'Europe/Amsterdam'));

    $session = new EnableBankingSession([
        'status' => 'authorized',
        'valid_until' => Carbon::parse('2026-11-22 15:00:00', 'Europe/Amsterdam'),
    ]);

    expect($session->daysRemaining())->toBe(88);
});
