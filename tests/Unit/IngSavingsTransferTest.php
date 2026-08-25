<?php

use App\Support\IngSavingsTransfer;

it('ziet een gewone oranje spaarrekening als koppelbaar, geen potje', function () {
    $parsed = IngSavingsTransfer::parseDestination('Naar Oranje spaarrekening L13628386');

    expect($parsed)->toMatchArray([
        'ref' => 'L13628386',
        'isSpaarpot' => false,
    ]);
    expect(IngSavingsTransfer::isSpaarpotDescription('Naar Oranje spaarrekening L13628386'))->toBeFalse();
});

it('ziet een vastgeplakt spaarpotnummer als potje', function () {
    $parsed = IngSavingsTransfer::parseDestination('Naar Oranje spaarrekeningC13134173');

    expect($parsed)->toMatchArray([
        'ref' => 'C13134173',
        'isSpaarpot' => true,
    ]);
    expect(IngSavingsTransfer::isSpaarpotDescription('Naar Oranje spaarrekeningC13134173'))->toBeTrue();
});

it('koppelt een transactie alleen aan het spaardoel met hetzelfde nummer', function () {
    expect(IngSavingsTransfer::matchesGoal(
        'Naar Oranje spaarrekening L13628386',
        'spaarrekening L13628386',
        null,
        'NAARORANJESPAARREKENINGL13628386',
    ))->toBeTrue();

    expect(IngSavingsTransfer::matchesGoal(
        'Naar Oranje spaarrekening L13628386',
        'spaarrekeningC13134173',
        null,
        'NAARORANJESPAARREKENINGL13628386',
    ))->toBeFalse();
});

it('laat een generieke naam spaarrekening niet alle ING-mutaties matchen', function () {
    expect(IngSavingsTransfer::matchesGoal(
        'Naar Oranje spaarrekening L13628386',
        'Oranje spaarrekening',
        null,
        'NAARORANJESPAARREKENINGL13628386',
    ))->toBeFalse();
});
