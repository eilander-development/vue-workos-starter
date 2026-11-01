<?php

use App\Models\Budget;
use App\Models\BudgetMonthValue;
use App\Models\Category;
use App\Models\User;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

test('budget rhythm requires authentication and a valid post', function () {
    $this->getJson('/api/sparen/budget-items/missing/rhythm')->assertUnauthorized();
    $this->actingAs(User::factory()->create());
    $this->getJson('/api/sparen/budget-items/missing/rhythm')->assertNotFound();
});

test('budget rhythm uses twelve historical reporting months across years without modifying budgets', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-01 12:00:00', 'Europe/Amsterdam'));
    $this->actingAs(User::factory()->create());
    $category = Category::query()->create(['key' => 'rhythm', 'name' => 'Verzekeringen', 'slug' => 'rhythm', 'type' => 'expense']);
    $budget = Budget::query()->create(['key' => 'rhythm', 'name' => 'Verzekering', 'budget' => 111.11, 'category_id' => $category->id]);
    foreach ([['feb', 2025, 113.50], ['jan', 2026, 111.11], ['feb', 2026, 500]] as [$month, $year, $amount]) {
        BudgetMonthValue::query()->create(['budget_id' => $budget->id, 'month_id' => $month, 'year' => $year, 'estimated' => $amount]);
    }
    $this->getJson('/api/sparen/budget-items/rhythm/rhythm')->assertOk()->assertJsonCount(12, 'months')
        ->assertJsonPath('months.0.month', '2025-02')->assertJsonPath('months.0.amount', 113.5)
        ->assertJsonPath('months.0.complete', true)->assertJsonPath('months.11.month', '2026-01')
        ->assertJsonPath('months.11.periodStart', '2026-01-15')->assertJsonPath('months.11.periodEnd', '2026-02-14')
        ->assertJsonPath('months.11.through', '2026-02-01')->assertJsonPath('months.11.complete', false)
        ->assertJsonPath('months.11.amount', 111.11);
    expect(BudgetMonthValue::query()->count())->toBe(3)->and((float) $budget->fresh()->budget)->toBe(111.11);
});
