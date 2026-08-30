<?php

use App\Models\Budget;
use App\Models\Category;
use App\Models\ImportRule;
use App\Models\SavingsGoal;
use App\Services\TransactionClassifier;

function savingsCatalog(): array
{
    $category = Category::query()->create([
        'key' => 'cat-spaargeld',
        'name' => 'Spaargeld',
        'slug' => 'spaargeld',
        'type' => 'saving',
    ]);

    $budget = Budget::query()->create([
        'key' => 'spaar-buffer',
        'category_id' => $category->id,
        'name' => 'Buffer',
        'budget' => 600,
    ]);

    $goal = SavingsGoal::query()->create([
        'key' => 'goal-buffer',
        'name' => 'Noodbuffer L13628386',
        'account_iban' => 'NL83INGB0131342031',
        'bank_name' => 'ING',
        'target_amount' => 4500,
        'initial_amount' => 0,
        'monthly_contribution' => 600,
        'kind' => 'goal',
        'budget_key' => $budget->key,
        'budget_keys' => [$budget->key],
    ]);

    $pot = SavingsGoal::query()->create([
        'key' => 'goal-pot',
        'name' => 'Boodschappen C13134173',
        'account_iban' => 'NL00INGB0000000000',
        'bank_name' => 'ING',
        'target_amount' => 0,
        'initial_amount' => 0,
        'monthly_contribution' => 400,
        'kind' => 'pot',
        'budget_keys' => [],
    ]);

    return compact('category', 'budget', 'goal', 'pot');
}

test('spaaropname van een spaardoel wordt niet aan de begrotingspost gekoppeld', function () {
    savingsCatalog();
    $result = app(TransactionClassifier::class)->classify(
        'Van Oranje spaarrekening L13628386',
        'NL83INGB0131342031',
        null,
        250.0,
    );

    expect($result['link_excluded'])->toBeTrue()
        ->and($result['budget_id'])->toBeNull()
        ->and($result['type'])->toBe('saving')
        ->and($result['link_exclusion_reason'])->toStartWith('Spaaropname');
});

test('spaarstorting van een spaardoel blijft gekoppeld aan de spaarpost', function () {
    $catalog = savingsCatalog();
    $result = app(TransactionClassifier::class)->classify(
        'Naar Oranje spaarrekening L13628386',
        'NL83INGB0131342031',
        null,
        -600.0,
    );

    expect($result['link_excluded'])->toBeFalse()
        ->and($result['budget_id'])->toBe($catalog['budget']->id)
        ->and($result['type'])->toBe('saving');
});

test('pot-opname en pot-storting worden uitgesloten zonder vaste namen', function () {
    savingsCatalog();
    $classifier = app(TransactionClassifier::class);

    $withdrawal = $classifier->classify('Van Oranje spaarrekeningC13134173', null, null, 46.31);
    $deposit = $classifier->classify('Naar Oranje spaarrekeningC13134173', null, null, -400.0);

    expect($withdrawal['link_excluded'])->toBeTrue()
        ->and($withdrawal['link_exclusion_reason'])->toStartWith('Pot-opname')
        ->and($deposit['link_excluded'])->toBeTrue()
        ->and($deposit['link_exclusion_reason'])->toStartWith('Pot-storting');
});

test('spaar-koppelregel matcht geen opnames', function () {
    $catalog = savingsCatalog();
    ImportRule::query()->create([
        'key' => 'rule-spaar',
        'name' => 'Sparen',
        'type' => 'keyword',
        'match_value' => 'Oranje spaarrekening',
        'match_field' => 'description',
        'is_active' => true,
        'category_id' => $catalog['category']->id,
        'budget_id' => $catalog['budget']->id,
    ]);

    $result = app(TransactionClassifier::class)->classify(
        'Van Oranje spaarrekening K14954441',
        null,
        null,
        46.31,
    );

    expect($result['link_excluded'])->toBeTrue()
        ->and($result['budget_id'])->toBeNull()
        ->and($result['link_exclusion_reason'])->toStartWith('Spaaropname');
});
