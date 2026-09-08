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

test('storting naar een genummerde spaarrekening betaalt de uitgavenpost en hangt aan dat spaardoel', function () {
    $catalog = savingsCatalog();

    $expenseCategory = Category::query()->create([
        'key' => 'cat-ovk',
        'name' => 'Overige Kosten',
        'slug' => 'overige-kosten',
        'type' => 'expense',
    ]);
    $expense = Budget::query()->create([
        'key' => 'ovk-2',
        'category_id' => $expenseCategory->id,
        'name' => 'Zorgkosten (eigen risico)',
        'budget' => 32,
    ]);
    $zorgkostenGoal = SavingsGoal::query()->create([
        'key' => 'goal-zorgkosten',
        'name' => 'spaarrekening a14304836',
        'account_iban' => '',
        'bank_name' => 'Zorgkosten',
        'target_amount' => 380,
        'initial_amount' => 224,
        'monthly_contribution' => 32,
        'kind' => 'goal',
        'budget_keys' => [],
    ]);

    ImportRule::query()->create([
        'key' => 'rule-buffer',
        'name' => 'Sparen Buffer Storting',
        'type' => 'keyword',
        'match_value' => 'Oranje spaarrekening',
        'match_field' => 'description',
        'is_active' => true,
        'category_id' => $catalog['category']->id,
        'budget_id' => $catalog['budget']->id,
    ]);
    $expenseRule = ImportRule::query()->create([
        'key' => 'rule-zorgkosten',
        'name' => 'Zorgkosten',
        'type' => 'keyword',
        'match_value' => 'Zorgkosten',
        'match_field' => 'counterparty',
        'is_active' => true,
        'category_id' => $expenseCategory->id,
        'budget_id' => $expense->id,
    ]);

    $result = app(TransactionClassifier::class)->classify(
        'Naar Oranje spaarrekening A14304836',
        'NL83INGB0004565868',
        'Zorgkosten',
        -32.0,
        'goal-buffer',
    );

    expect($result['type'])->toBe('expense')
        ->and($result['budget_id'])->toBe($expense->id)
        ->and($result['budget_key'])->toBe('ovk-2')
        ->and($result['rule_id'])->toBe($expenseRule->id)
        ->and($result['savings_goal_key'])->toBe($zorgkostenGoal->key)
        ->and($result['link_excluded'] ?? false)->toBeFalse();
});
