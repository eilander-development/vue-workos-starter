<?php

use App\Models\Category;
use App\Models\EnableBankingSession;
use App\Models\Transaction;
use App\Models\User;

test('catalog en ledger gebruiken aparte connecties', function () {
    expect((new Category)->getConnectionName())->toBe('catalog')
        ->and((new Transaction)->getConnectionName())->toBe('ledger')
        ->and((new EnableBankingSession)->getConnectionName())->toBe('ledger')
        ->and((new User)->getConnectionName())->toBe('ledger');
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
