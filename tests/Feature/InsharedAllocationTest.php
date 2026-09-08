<?php

use App\Models\Budget;
use App\Models\Category;
use App\Models\ImportRule;
use App\Models\Transaction;
use App\Services\SparenStateService;

test('inshared-incasso wordt verdeeld over woon- en autoverzekering', function () {
    $verzekeringen = Category::query()->create([
        'key' => 'cat-verzekeringen',
        'name' => 'Verzekeringen',
        'slug' => 'verzekeringen',
        'type' => 'expense',
    ]);
    $vervoer = Category::query()->create([
        'key' => 'cat-vervoer',
        'name' => 'Vervoersmiddelen',
        'slug' => 'vervoersmiddelen',
        'type' => 'expense',
    ]);

    Budget::query()->create([
        'key' => 'verz-2',
        'category_id' => $verzekeringen->id,
        'name' => 'Woonverzekering (InShared)',
        'budget' => 41.29,
    ]);
    Budget::query()->create([
        'key' => 'verv-2',
        'category_id' => $vervoer->id,
        'name' => 'Verzekering',
        'budget' => 113.58,
    ]);

    $rule = ImportRule::query()->create([
        'key' => 'rule-14',
        'name' => 'InShared Verzekering',
        'type' => 'description',
        'match_value' => 'InShared',
        'match_field' => 'description',
        'is_active' => true,
        'category_id' => $verzekeringen->id,
        'budget_id' => Budget::query()->where('key', 'verz-2')->value('id'),
        'allocations' => [
            ['budgetItemId' => 'verz-2', 'amount' => 41.29],
            ['budgetItemId' => 'verv-2', 'amount' => 113.58],
        ],
    ]);

    Transaction::query()->create([
        'key' => 'tx-inshared',
        'description' => 'InShared Nederland B.V. Automatische incasso',
        'amount' => -154.86,
        'date' => '2026-09-01',
        'type' => 'expense',
        'category_id' => $verzekeringen->id,
        'budget_id' => Budget::query()->where('key', 'verz-2')->value('id'),
        'rule_id' => $rule->id,
        'source_type' => 'api',
        'is_pending' => false,
    ]);

    $state = app(SparenStateService::class)->build(2026);
    $aug = collect($state['monthlyBudgets'])->firstWhere('monthId', 'aug');
    $woon = collect($aug['items'])->firstWhere('id', 'verz-2');
    $auto = collect($aug['items'])->firstWhere('id', 'verv-2');
    $tx = collect($state['transactions'])->firstWhere('id', 'tx-inshared');

    expect($woon['paidOrReceived'])->toBe(41.29)
        ->and($auto['paidOrReceived'])->toBe(113.57)
        ->and($tx['allocations'])->toEqual([
            ['budgetItemId' => 'verz-2', 'amount' => 41.29],
            ['budgetItemId' => 'verv-2', 'amount' => 113.57],
        ]);
});
