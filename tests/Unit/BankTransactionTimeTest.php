<?php

use App\Support\BankTransactionTime;

it('leest de tijd uit een expliciet datetime-veld', function () {
    expect(BankTransactionTime::extract(['booking_datetime' => '2026-08-20T14:32:00']))->toBe('14:32');
});

it('rekent een datetime met tijdzone om naar Amsterdamse tijd', function () {
    expect(BankTransactionTime::extract(['booking_datetime' => '2026-08-20T12:32:00Z']))->toBe('14:32');
});

it('negeert velden zonder tijd', function () {
    expect(BankTransactionTime::extract([
        'booking_date' => '2026-08-20',
        'transaction_date' => '2026-08-20',
    ]))->toBeNull();
});

it('leest de tijd uit een Datum/Tijd-regel in de omschrijving', function () {
    expect(BankTransactionTime::extract([
        'remittance_information' => [
            'Naam: Kruidvat 2536',
            'Datum/Tijd: 20-08-2026 14:32',
        ],
    ]))->toBe('14:32');
});

it('neemt boekdatum uit booking_date, niet uit factuur- of transactiedatum', function () {
    expect(BankTransactionTime::bookingDate([
        'booking_date' => '2026-08-24',
        'transaction_date' => '2026-08-17',
        'value_date' => '2026-08-17',
        'date' => '2026-08-17',
    ]))->toBe('2026-08-24');
});

it('leest boekdatum uit nested raw als het topniveau die niet heeft', function () {
    expect(BankTransactionTime::bookingDate([
        'date' => '2026-08-17',
        'raw' => ['booking_date' => '2026-08-24'],
    ]))->toBe('2026-08-24');
});

it('gebruikt alleen booking_datetime voor de boekingstijd', function () {
    expect(BankTransactionTime::extractBookingTime([
        'booking_datetime' => '2026-08-24T09:15:00',
        'remittance_information' => ['Datum/Tijd: 17-08-2026 14:32'],
    ]))->toBe('09:15');

    expect(BankTransactionTime::extractBookingTime([
        'remittance_information' => ['Datum/Tijd: 17-08-2026 14:32'],
    ]))->toBeNull();
});

it('leest de tijd uit een ING-betaalautomaatregel', function () {
    expect(BankTransactionTime::extract([
        'remittance_information' => [
            'Omschrijving: BEA, Apple Pay Kruidvat 2536, PAS123 23.08.26/14.21 UTRECHT',
        ],
    ]))->toBe('14:21');
});

it('leest de tijd uit het geneste raw-blok', function () {
    expect(BankTransactionTime::extract([
        'date' => '2026-08-20',
        'raw' => ['remittance_information' => ['Tijd: 09:05']],
    ]))->toBe('09:05');
});

it('ziet middernacht als datum zonder tijd', function () {
    expect(BankTransactionTime::extract(['booking_datetime' => '2026-08-20T00:00:00']))->toBeNull();
});

it('negeert onmogelijke kloktijden', function () {
    expect(BankTransactionTime::extract(['remittance_information' => ['Kenmerk: 99:99']]))->toBeNull();
});
