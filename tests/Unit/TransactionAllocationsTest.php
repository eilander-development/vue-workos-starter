<?php

use App\Support\TransactionAllocations;

it('schaalt een InShared-verdeling naar het incassobedrag', function () {
    $scaled = TransactionAllocations::scaleToAmount([
        ['budgetItemId' => 'verz-2', 'amount' => 41.29],
        ['budgetItemId' => 'verv-2', 'amount' => 113.58],
    ], 154.86);

    expect($scaled)->toHaveCount(2)
        ->and($scaled[0]['budgetItemId'])->toBe('verz-2')
        ->and($scaled[0]['amount'])->toBe(41.29)
        ->and($scaled[1]['budgetItemId'])->toBe('verv-2')
        ->and($scaled[1]['amount'])->toBe(113.57)
        ->and($scaled[0]['amount'] + $scaled[1]['amount'])->toBe(154.86);
});

it('negeert een enkele post als verdeling', function () {
    expect(TransactionAllocations::normalize([
        ['budgetItemId' => 'verz-2', 'amount' => 41.29],
    ]))->toBe([]);
});
