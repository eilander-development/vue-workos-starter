<?php

use App\Models\Category;
use App\Models\EnableBankingSession;
use App\Models\Transaction;
use App\Models\User;

test('catalog en ledger gebruiken aparte sqlite-connecties', function () {
    expect((new Category)->getConnectionName())->toBe('catalog')
        ->and((new Transaction)->getConnectionName())->toBe('sqlite')
        ->and((new EnableBankingSession)->getConnectionName())->toBe('sqlite')
        ->and((new User)->getConnectionName())->toBe('sqlite');
});

test('sparen-state leest catalogus zonder ledger-transacties', function () {
    $this->actingAs(User::factory()->create());

    Category::query()->create([
        'key' => 'cat-test',
        'name' => 'Testgroep',
        'slug' => 'testgroep',
        'type' => 'expense',
    ]);

    $this->getJson('/api/sparen/state')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Testgroep']);
});
