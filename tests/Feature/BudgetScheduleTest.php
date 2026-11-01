<?php

use App\Models\Budget;
use App\Models\BudgetMonthValue;
use App\Models\Category;
use App\Models\User;
use App\Services\BudgetProjection;
use App\Services\ForecastService;
use App\Services\ReportingPeriod;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-01 12:00:00', 'Europe/Amsterdam'));
    $this->actingAs(User::factory()->create());
    $category = Category::create(['key' => 'schedule', 'name' => 'Woning', 'slug' => 'schedule', 'type' => 'expense']);
    $this->budget = Budget::create(['key' => 'school', 'name' => 'Laptops', 'category_id' => $category->id, 'budget' => 36]);
    foreach (ReportingPeriod::SPAREN_MONTH_IDS as $month) {
        BudgetMonthValue::create(['budget_id' => $this->budget->id, 'year' => 2026, 'month_id' => $month, 'estimated' => $month === 'dec' ? 40 : 36]);
    }
});
afterEach(fn () => CarbonImmutable::setTestNow());

test('schedule lists only available past current and future years without writing projected values', function () {
    BudgetMonthValue::create(['budget_id' => $this->budget->id, 'year' => 2024, 'month_id' => 'jan', 'estimated' => 30]);
    BudgetMonthValue::create(['budget_id' => $this->budget->id, 'year' => 2028, 'month_id' => 'jan', 'estimated' => 45]);
    $this->getJson('/api/sparen/budget-items/school/schedule')->assertOk()
        ->assertJsonPath('schedules.0.year', 2024)->assertJsonPath('schedules.1.year', 2026)->assertJsonPath('schedules.2.year', 2028)
        ->assertJsonPath('schedules.2.period', 'future')->assertJsonPath('schedules.2.months.0.amount', 45)->assertJsonCount(3, 'schedules');
    expect(BudgetMonthValue::count())->toBe(14);
});

test('applying a forecast exposes the new year schedule and preserves the previous year', function () {
    $service = app(ForecastService::class);
    $source = $service->source('2027-01');
    expect($source['basisMonth'])->toBe('2026-12');
    $forecast = $service->save(['name' => '2027', 'startMonth' => '2027-01', 'sourceHash' => $source['sourceHash'], 'rules' => []], 'Tester');
    expect($forecast->snapshot['months'][1]['before']['expenses'])->toEqual(36)
        ->and($forecast->snapshot['months'][1]['after']['expenses'])->toEqual(40);
    $service->apply($forecast);
    $this->getJson('/api/sparen/budget-items/school/schedule')->assertOk()
        ->assertJsonPath('schedules.0.year', 2026)->assertJsonPath('schedules.0.months.1.amount', 36)
        ->assertJsonPath('schedules.1.year', 2027)->assertJsonPath('schedules.1.months.1.amount', 40)
        ->assertJsonPath('schedules.1.months.1.origin', 'forecast');
    expect(BudgetMonthValue::count())->toBe(12);
});

test('editing a future year does not alter earlier year values or the global fallback', function () {
    $revision = $this->getJson('/api/sparen/budget-items/school/schedule')->json('revision');
    $this->putJson('/api/sparen/budget-items/school', ['name' => 'Laptops', 'group' => 'Woning', 'estimated' => 50,
        'year' => 2027, 'monthlyAmounts' => ['feb' => 50], 'scheduleRevision' => $revision])->assertOk();
    expect((new BudgetProjection)->resolve($this->budget->fresh(), '2026-02')['amount'])->toBe(36.0)
        ->and((new BudgetProjection)->resolve($this->budget->fresh(), '2027-02')['amount'])->toBe(50.0)
        ->and((float) $this->budget->fresh()->budget)->toBe(36.0);
    $this->putJson('/api/sparen/budget-items/school', ['name' => 'Laptops', 'group' => 'Woning', 'year' => 2027,
        'monthlyAmounts' => ['feb' => 60], 'scheduleRevision' => $revision])->assertConflict();
    expect((new BudgetProjection)->resolve($this->budget->fresh(), '2027-02')['amount'])->toBe(50.0);
});

test('saving an unchanged projected year preserves forecast undo and does not materialize overrides', function () {
    $service = app(ForecastService::class);
    $source = $service->source('2027-01');
    $forecast = $service->save(['name' => '2027', 'startMonth' => '2027-01', 'sourceHash' => $source['sourceHash'], 'rules' => []], 'Tester');
    $service->apply($forecast);
    $revision = $this->getJson('/api/sparen/budget-items/school/schedule')->json('revision');
    $this->putJson('/api/sparen/budget-items/school', ['name' => 'Laptops', 'group' => 'Woning', 'estimated' => 40, 'year' => 2027,
        'scheduleRevision' => $revision, 'monthlyAmounts' => array_fill_keys(ReportingPeriod::SPAREN_MONTH_IDS, 40)])->assertOk();
    expect(BudgetMonthValue::where('budget_id', $this->budget->id)->where('year', 2027)->count())->toBe(0);
    $service->revert($forecast->fresh(), '2027-01');
    expect((new BudgetProjection)->resolve($this->budget->fresh(), '2027-02')['amount'])->toBe(36.0);
});

test('the applied schema on the basis date takes precedence over an exceptional earlier month', function () {
    $service = app(ForecastService::class);
    $source = $service->source('2027-01');
    $forecast = $service->save(['name' => '2027', 'startMonth' => '2027-01', 'sourceHash' => $source['sourceHash'], 'rules' => []], 'Tester');
    $service->apply($forecast);
    BudgetMonthValue::create(['budget_id' => $this->budget->id, 'year' => 2027, 'month_id' => 'feb', 'estimated' => 45]);
    expect($service->source('2027-05')['items'][0]['latestFixed']['amount'])->toEqual(40);
});

test('a monthly series that started during the prior year continues from the latest base amount', function () {
    foreach (ReportingPeriod::SPAREN_MONTH_IDS as $index => $month) {
        BudgetMonthValue::where('budget_id', $this->budget->id)->where('month_id', $month)->update(['estimated' => $index >= 8 ? 39.30 : 0]);
    }
    $service = app(ForecastService::class);
    foreach (['2027-01', '2027-05'] as $start) {
        $source = $service->source($start);
        expect($source['items'][0]['cadenceReason'])->toBe('monthly-start')->and($source['items'][0]['forecastMonthly'])->toBeTrue();
        $forecast = $service->save(['name' => 'Laptops', 'startMonth' => $start, 'sourceHash' => $source['sourceHash'], 'rules' => []], 'Tester');
        foreach ($forecast->snapshot['months'] as $month) {
            expect($month['after']['expenses'])->toEqual(39.30);
        }
    }
    $source = $service->source('2027-01');
    $legacy = ['itemId' => 'school', 'basis' => 'latest', 'included' => true, 'excludedMonths' => [], 'rememberBasis' => false, 'everyMonth' => false];
    $forecast = $service->save(['name' => 'Bestaande prognose', 'startMonth' => '2027-01', 'sourceHash' => $source['sourceHash'], 'rules' => [], 'settings' => [$legacy]], 'Tester');
    expect($forecast->snapshot['months'][0]['after']['expenses'])->toEqual(39.30)
        ->and(array_key_exists('everyMonth', $forecast->snapshot['settings'][0]))->toBeFalse();
    $legacy['cadenceOverride'] = true;
    $forecast = $service->save(['name' => 'Bewust maandprofiel', 'startMonth' => '2027-01', 'sourceHash' => $source['sourceHash'], 'rules' => [], 'settings' => [$legacy]], 'Tester');
    expect($forecast->snapshot['months'][0]['after']['expenses'])->toEqual(0);
    $this->budget->update(['forecast_basis' => 'profile']);
    expect($service->source('2027-01')['items'][0]['forecastMonthly'])->toBeTrue();
    $this->budget->update(['forecast_basis' => 'latest', 'forecast_monthly' => false]);
    expect($service->source('2027-01')['items'][0]['forecastMonthly'])->toBeTrue()
        ->and($service->source('2027-01')['items'][0]['cadenceReason'])->toBe('monthly-start');
});

test('schedule endpoint requires authentication', function () {
    auth()->logout();
    $this->getJson('/api/sparen/budget-items/school/schedule')->assertUnauthorized();
});

test('a monthly payment set to zero before the forecast is not revived from last year', function () {
    BudgetMonthValue::where('budget_id', $this->budget->id)->where('month_id', 'dec')->update(['estimated' => 0]);
    $service = app(ForecastService::class);
    $source = $service->source('2027-01');
    expect($source['items'][0]['latestFixed']['amount'])->toEqual(0)
        ->and($source['items'][0]['cadenceReason'])->toBe('monthly-stop');
    $forecast = $service->save(['name' => 'Gestopt', 'startMonth' => '2027-01', 'sourceHash' => $source['sourceHash'], 'rules' => []], 'Tester');
    foreach ($forecast->snapshot['months'] as $month) {
        expect($month['after']['expenses'])->toEqual(0);
    }
});
