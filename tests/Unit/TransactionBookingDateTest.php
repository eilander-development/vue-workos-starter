<?php

use App\DTOs\TransactionDTO;

it('zet posted_at op booking_date, niet op de factuurdatum in remittance', function () {
    $dto = TransactionDTO::fromEnableBanking([
        'booking_date' => '2026-08-24',
        'transaction_date' => '2026-08-17',
        'value_date' => '2026-08-17',
        'credit_debit_indicator' => 'DBIT',
        'transaction_amount' => ['amount' => 84.74, 'currency' => 'EUR'],
        'creditor' => ['name' => 'KPN B.V.'],
        'remittance_information' => [
            'KPN B.V. Factuur 11-08-2026, klantnummer 9031348742',
        ],
    ]);

    expect($dto->posted_at)->toBe('2026-08-24');
    expect($dto->time)->toBeNull();
});

it('neemt boekingstijd alleen uit booking_datetime', function () {
    $dto = TransactionDTO::fromEnableBanking([
        'booking_date' => '2026-08-24',
        'booking_datetime' => '2026-08-24T09:15:00',
        'credit_debit_indicator' => 'DBIT',
        'transaction_amount' => ['amount' => 1, 'currency' => 'EUR'],
        'remittance_information' => ['Datum/Tijd: 17-08-2026 14:32'],
    ]);

    expect($dto->posted_at)->toBe('2026-08-24');
    expect($dto->time)->toBe('09:15');
});
