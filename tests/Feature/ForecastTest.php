<?php

use App\Models\Budget;
use App\Models\BudgetAmountChange;
use App\Models\BudgetMonthValue;
use App\Models\Category;
use App\Models\Forecast;
use App\Models\User;
use App\Services\BudgetProjection;
use App\Services\ForecastService;
use App\Services\ReportingPeriod;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-31 12:00:00', 'Europe/Amsterdam'));
    $this->actingAs(User::factory()->create());
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function forecastBudget(string $key = 'income', float $amount = 3000, string $type = 'income'): Budget
{
    $category = Category::query()->firstOrCreate(['key' => 'forecast-'.$type], [
        'name' => 'Forecast '.$type, 'slug' => 'forecast-'.$type, 'type' => $type,
    ]);

    return Budget::query()->create(['key' => $key, 'name' => $key, 'category_id' => $category->id, 'budget' => $amount]);
}

function forecastRule(string $itemId = 'income', string $effective = '2027-05', string $operation = 'percent', float $value = 3, string $id = 'r1'): array
{
    return ['id' => $id, 'itemId' => $itemId, 'effectiveMonth' => $effective, 'operation' => $operation, 'value' => $value, 'enabled' => true];
}

function forecastPayload(array $rules, string $start = '2027-05'): array
{
    $source = app(ForecastService::class)->source($start);

    return ['name' => 'Mijn prognose', 'startMonth' => $start, 'sourceHash' => $source['sourceHash'], 'rules' => $rules];
}

function saveForecast(array $rules, string $start = '2027-05'): Forecast
{
    return app(ForecastService::class)->save(forecastPayload($rules, $start), 'Tester');
}

test('forecast routes require authentication', function () {
    auth()->logout();
    $this->getJson('/api/sparen/forecasts')->assertUnauthorized();
    $this->postJson('/api/sparen/forecasts', [])->assertUnauthorized();
});

test('current reporting month is excluded including the days before the reporting boundary', function () {
    $this->getJson('/api/sparen/forecasts/source?startMonth=2026-10')->assertUnprocessable();
    $this->getJson('/api/sparen/forecasts/source?startMonth=2026-11')->assertOk();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10', 'Europe/Amsterdam'));
    expect(app(ForecastService::class)->firstFutureMonth())->toBe('2026-11');
});

test('saved forecast has twelve independent months and zero opening balances', function () {
    forecastBudget();
    forecastBudget('rent', 1000, 'expense');
    forecastBudget('saving', 200, 'saving');
    $this->postJson('/api/sparen/forecasts', forecastPayload([forecastRule()]))
        ->assertCreated()->assertJsonCount(12, 'snapshot.months')
        ->assertJsonPath('snapshot.months.0.month', '2027-05')
        ->assertJsonPath('snapshot.months.11.month', '2028-04')
        ->assertJsonPath('snapshot.months.0.before.free', 1800)
        ->assertJsonPath('snapshot.months.0.after.free', 1890)
        ->assertJsonPath('snapshot.months.1.after.free', 1890);
    expect(BudgetAmountChange::query()->count())->toBe(0);
});

test('quarterly pattern is inherited from previous year and fixed amounts persist after the horizon', function () {
    $budget = forecastBudget('insurance', 300, 'expense');
    foreach (ReportingPeriod::SPAREN_MONTH_IDS as $i => $month) {
        BudgetMonthValue::query()->create(['budget_id' => $budget->id, 'month_id' => $month, 'year' => 2026, 'estimated' => in_array($i, [0, 3, 6, 9]) ? 300 : 0]);
    }
    $forecast = saveForecast([forecastRule('insurance', '2027-05', 'percent', 10)]);
    expect($forecast->snapshot['specs'][0]['firstPayment'])->toBe('2027-07');
    $this->postJson("/api/sparen/forecasts/{$forecast->key}/apply", ['revision' => app(ForecastService::class)->payload($forecast->fresh())['revision']])->assertOk();
    $projection = new BudgetProjection;
    expect($projection->resolve($budget, '2027-04')['amount'])->toBe(300.0)
        ->and($projection->resolve($budget, '2027-05')['amount'])->toBe(0.0)
        ->and($projection->resolve($budget, '2027-07')['amount'])->toBe(330.0)
        ->and($projection->resolve($budget, '2029-07')['amount'])->toBe(330.0)
        ->and($projection->resolve($budget, '2029-08')['amount'])->toBe(0.0);
    $this->getJson('/api/sparen/state?year=2027')->assertOk()->assertJsonPath('monthlyBudgets.6.items.0.actual', 330);
});

test('non calendar quarterly pattern retains its payment months', function () {
    $budget = forecastBudget('insurance', 300, 'expense');
    foreach (ReportingPeriod::SPAREN_MONTH_IDS as $i => $month) {
        BudgetMonthValue::query()->create(['budget_id' => $budget->id, 'month_id' => $month, 'year' => 2026, 'estimated' => in_array($i, [1, 4, 7, 10]) ? 300 : 0]);
    }
    $forecast = saveForecast([forecastRule('insurance', '2027-06', 'delta', 30)], '2027-06');
    app(ForecastService::class)->apply($forecast);
    $projection = new BudgetProjection;
    expect($projection->resolve($budget, '2027-05')['amount'])->toBe(300.0)
        ->and($projection->resolve($budget, '2027-07')['amount'])->toBe(0.0)
        ->and($projection->resolve($budget, '2027-08')['amount'])->toBe(330.0);
});

test('ordered changes use the previous scenario amount without monthly compounding', function () {
    $budget = forecastBudget();
    $forecast = saveForecast([
        forecastRule('income', '2027-09', 'percent', 2, 'r2'),
        forecastRule(),
    ]);
    expect($forecast->snapshot['months'][0]['after']['income'])->toEqual(3090)
        ->and($forecast->snapshot['months'][3]['after']['income'])->toEqual(3090)
        ->and($forecast->snapshot['months'][4]['after']['income'])->toEqual(3151.8)
        ->and($forecast->snapshot['months'][5]['after']['income'])->toEqual(3151.8);
    app(ForecastService::class)->apply($forecast);
    expect((new BudgetProjection)->resolve($budget, '2028-06')['amount'])->toBe(3151.8);
});

test('varying monthly amounts are converted individually to concrete amounts', function () {
    $budget = forecastBudget('utilities', 100, 'expense');
    BudgetMonthValue::query()->create(['budget_id' => $budget->id, 'month_id' => 'jun', 'year' => 2027, 'estimated' => 200]);
    $forecast = saveForecast([forecastRule('utilities', '2027-05', 'percent', 10)]);
    app(ForecastService::class)->apply($forecast);
    $projection = new BudgetProjection;
    expect($projection->resolve($budget, '2027-05')['amount'])->toBe(110.0)
        ->and($projection->resolve($budget, '2027-06')['amount'])->toBe(220.0)
        ->and($projection->resolve($budget, '2030-06')['amount'])->toBe(220.0);
});

test('saved snapshot is immutable while a copy recalculates on fresh data', function () {
    $budget = forecastBudget();
    $forecast = saveForecast([forecastRule()]);
    $snapshot = $forecast->snapshot;
    $budget->update(['budget' => 4000]);
    $this->getJson("/api/sparen/forecasts/{$forecast->key}")->assertOk()->assertJsonPath('snapshot.months.0.after.income', 3090);
    $copy = app(ForecastService::class)->save([...forecastPayload([forecastRule()]), 'copiedFrom' => $forecast->key], 'Tester');
    expect($copy->snapshot['months'][0]['after']['income'])->toEqual(4120)
        ->and($copy->copied_from)->toBe($forecast->key)
        ->and($forecast->fresh()->snapshot)->toEqual($snapshot);
});

test('changed data blocks stale saves and stale applications', function () {
    $budget = forecastBudget();
    $payload = forecastPayload([forecastRule()]);
    $forecast = saveForecast([forecastRule()]);
    $budget->update(['budget' => 4000]);
    $this->postJson('/api/sparen/forecasts', $payload)->assertConflict();
    $this->postJson("/api/sparen/forecasts/{$forecast->key}/apply", ['revision' => app(ForecastService::class)->payload($forecast->fresh())['revision']])->assertConflict();
    expect(BudgetAmountChange::query()->count())->toBe(0);
});

test('a forecast can only be applied once and only before its starting month', function () {
    forecastBudget();
    $forecast = saveForecast([forecastRule()]);
    $this->postJson("/api/sparen/forecasts/{$forecast->key}/apply", ['revision' => app(ForecastService::class)->payload($forecast->fresh())['revision']])->assertOk();
    $this->postJson("/api/sparen/forecasts/{$forecast->key}/apply", ['revision' => app(ForecastService::class)->payload($forecast->fresh())['revision']])->assertConflict();
    $later = saveForecast([forecastRule('income', '2027-06')], '2027-06');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-20', 'Europe/Amsterdam'));
    $this->postJson("/api/sparen/forecasts/{$later->key}/apply", ['revision' => app(ForecastService::class)->payload($later->fresh())['revision']])->assertUnprocessable();
});

test('known later scheduled changes are preserved', function () {
    $budget = forecastBudget();
    $later = saveForecast([forecastRule('income', '2027-09', 'set', 3500)], '2027-09');
    app(ForecastService::class)->apply($later);
    $earlier = saveForecast([forecastRule('income', '2027-05', 'delta', 100)]);
    expect($earlier->snapshot['specs'][0]['endMonth'])->toBe('2027-09')
        ->and($earlier->snapshot['months'][0]['after']['income'])->toEqual(3100)
        ->and($earlier->snapshot['months'][4]['after']['income'])->toEqual(3500);
    app(ForecastService::class)->apply($earlier);
    $projection = new BudgetProjection;
    expect($projection->resolve($budget, '2027-05')['amount'])->toBe(3100.0)
        ->and($projection->resolve($budget, '2027-09')['amount'])->toBe(3500.0);
});

test('different concrete amounts already planned beyond the horizon are preserved', function () {
    $budget = forecastBudget();
    BudgetMonthValue::query()->create(['budget_id' => $budget->id, 'month_id' => 'jun', 'year' => 2029, 'estimated' => 4000]);
    $forecast = saveForecast([forecastRule()]);
    app(ForecastService::class)->apply($forecast);
    $projection = new BudgetProjection;
    expect($projection->resolve($budget, '2028-06')['amount'])->toBe(3090.0)
        ->and($projection->resolve($budget, '2029-06')['amount'])->toBe(4000.0)
        ->and($projection->resolve($budget, '2030-06')['amount'])->toBe(4000.0);
});

test('manual edits take priority and protect against undo', function () {
    $budget = forecastBudget();
    $forecast = saveForecast([forecastRule()]);
    app(ForecastService::class)->apply($forecast);
    BudgetMonthValue::query()->create(['budget_id' => $budget->id, 'month_id' => 'jun', 'year' => 2027, 'estimated' => 4100]);
    expect((new BudgetProjection)->resolve($budget, '2027-06')['amount'])->toBe(4100.0);
    $this->postJson("/api/sparen/forecasts/{$forecast->key}/revert", ['fromMonth' => '2027-05'])->assertConflict();
});

test('manual edits to the default amount take priority and protect against undo', function () {
    $budget = forecastBudget();
    $forecast = saveForecast([forecastRule()]);
    app(ForecastService::class)->apply($forecast);
    $budget->update(['budget' => 4100]);
    expect((new BudgetProjection)->resolve($budget, '2027-06')['amount'])->toBe(4100.0);
    $this->postJson("/api/sparen/forecasts/{$forecast->key}/revert", ['fromMonth' => '2027-05'])->assertConflict();
});

test('undo keeps elapsed months and restores future amounts with a visible history', function () {
    $budget = forecastBudget();
    $forecast = saveForecast([forecastRule()]);
    app(ForecastService::class)->apply($forecast);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-08-20', 'Europe/Amsterdam'));
    $this->postJson("/api/sparen/forecasts/{$forecast->key}/revert", ['fromMonth' => '2027-08'])->assertUnprocessable();
    $this->postJson("/api/sparen/forecasts/{$forecast->key}/revert", ['fromMonth' => '2027-09'])->assertOk()->assertJsonPath('revertedFrom', '2027-09');
    $projection = new BudgetProjection;
    expect($projection->resolve($budget, '2027-08')['amount'])->toBe(3090.0)
        ->and($projection->resolve($budget, '2027-09')['amount'])->toBe(3000.0)
        ->and($forecast->fresh()->snapshot['months'][5]['after']['income'])->toEqual(3090);
    $this->getJson('/api/sparen/budget-items/income/history')->assertOk()->assertJsonPath('0.forecastName', 'Mijn prognose')->assertJsonPath('0.revertedFrom', '2027-09');
});

test('undo refuses to overwrite changes applied in a later forecast', function () {
    forecastBudget();
    $first = saveForecast([forecastRule()]);
    app(ForecastService::class)->apply($first);
    $later = saveForecast([forecastRule('income', '2027-09', 'delta', 100)], '2027-09');
    app(ForecastService::class)->apply($later);
    $this->postJson("/api/sparen/forecasts/{$first->key}/revert", ['fromMonth' => '2027-05'])->assertConflict();
});

test('fully reverted future changes do not create a boundary for new forecasts', function () {
    forecastBudget();
    $first = saveForecast([forecastRule()]);
    app(ForecastService::class)->apply($first);
    app(ForecastService::class)->revert($first, '2026-11');
    $new = saveForecast([forecastRule('income', '2027-01', 'delta', 100)], '2027-01');
    expect($new->snapshot['specs'][0]['endMonth'])->toBeNull();
});

test('copy exposes applied rules so the interface can disable them', function () {
    forecastBudget();
    $forecast = saveForecast([forecastRule()]);
    app(ForecastService::class)->apply($forecast);
    $this->getJson("/api/sparen/forecasts/{$forecast->key}")->assertOk()->assertJsonPath('appliedRuleIds.0', 'r1');
    $disabled = [...forecastRule(), 'enabled' => false];
    $copy = saveForecast([$disabled]);
    expect($copy->snapshot['months'][0]['after']['income'])->toEqual(3090);
});

test('invalid rules cannot modify the catalog', function () {
    forecastBudget();
    foreach ([
        [forecastRule('missing')],
        [forecastRule('income', '2027-05', 'set', -1)],
        [forecastRule('income', '2027-05', 'percent', -101)],
        [forecastRule(), forecastRule('income', '2027-05', 'delta', 10, 'r2')],
        [forecastRule('income', '2028-05')],
    ] as $rules) {
        $this->postJson('/api/sparen/forecasts', forecastPayload($rules))->assertUnprocessable();
    }
    expect(Forecast::query()->count())->toBe(0)->and(BudgetAmountChange::query()->count())->toBe(0);
});

test('setting a recurring amount to zero preserves its schedule for a later scenario change', function () {
    forecastBudget();
    $forecast = saveForecast([
        forecastRule('income', '2027-05', 'set', 0),
        forecastRule('income', '2027-07', 'set', 3200, 'r2'),
    ]);
    expect($forecast->snapshot['months'][0]['after']['income'])->toEqual(0)
        ->and($forecast->snapshot['months'][2]['after']['income'])->toEqual(3200);
});

test('historical loose expenses are references rather than recurring forecast expenses', function () {
    forecastBudget();
    $extra = forecastBudget('one-off', 0, 'expense');
    BudgetMonthValue::query()->create([
        'budget_id' => $extra->id, 'month_id' => 'aug', 'year' => 2026, 'estimated' => 250,
        'entries' => [['id' => 'entry', 'description' => 'Eenmalige aankoop', 'amount' => 250]],
    ]);
    $source = app(ForecastService::class)->source('2027-08');
    $cell = $source['months'][0]['items']['one-off'];
    expect($cell['amount'])->toBe(0)->and($cell['scheduled'])->toBeFalse()
        ->and($cell['excludedCost']['kind'])->toBe('historical')
        ->and($cell['excludedCost']['sourceMonth'])->toBe('2026-08')
        ->and($cell['excludedCost']['amount'])->toBe(250.0);
    $forecast = saveForecast([forecastRule('income', '2027-08')], '2027-08');
    expect($forecast->snapshot['version'])->toBe(3)
        ->and($forecast->snapshot['months'][0]['before']['expenses'])->toEqual(0)
        ->and($forecast->snapshot['months'][0]['before']['free'])->toEqual(3000)
        ->and($forecast->snapshot['months'][0]['warnings'][0]['entries'][0]['description'])->toBe('Eenmalige aankoop');
});

test('deferred payments are excluded by default even without loose budget lines', function () {
    forecastBudget();
    forecastBudget('Klarna', 250, 'expense');
    forecastBudget('Creditcard', 150, 'expense');
    forecastBudget('groceries', 500, 'expense');
    $forecast = saveForecast([forecastRule()]);
    expect($forecast->snapshot['months'][0]['before']['expenses'])->toEqual(500)
        ->and($forecast->snapshot['months'][0]['before']['free'])->toEqual(2500)
        ->and($forecast->snapshot['months'][0]['warnings'])->toHaveCount(2)
        ->and($forecast->snapshot['months'][0]['warnings'][0]['kind'])->toBe('historical');
});

test('explicit future extras remain distinct from historical references and do not repeat annually', function () {
    forecastBudget();
    $extra = forecastBudget('extra', 0, 'expense');
    BudgetMonthValue::query()->create([
        'budget_id' => $extra->id, 'month_id' => 'jan', 'year' => 2027, 'estimated' => 400,
        'entries' => [['id' => 'entry', 'description' => 'Geplande aankoop', 'amount' => 400]],
    ]);
    $source = app(ForecastService::class)->source('2027-01');
    expect($source['months'][0]['items']['extra']['excludedCost']['kind'])->toBe('planned')
        ->and($source['months'][0]['items']['extra']['excludedCost']['sourceMonth'])->toBe('2027-01')
        ->and($source['months'][12]['items']['extra']['excludedCost']['kind'])->toBe('historical')
        ->and($source['months'][12]['items']['extra']['amount'])->toBe(0);
    $forecast = saveForecast([forecastRule('income', '2027-01')], '2027-01');
    expect($forecast->snapshot['months'][0]['before']['free'])->toEqual(3000)
        ->and($forecast->snapshot['months'][0]['warnings'][0]['amount'])->toEqual(400);
});

test('a fixed installment can explicitly be included and ordinary costs can be marked extra', function () {
    forecastBudget();
    $card = forecastBudget('Creditcard', 150, 'expense');
    $card->update(['forecast_mode' => 'fixed']);
    $other = forecastBudget('other', 250, 'expense');
    $other->update(['forecast_mode' => 'extra']);
    $forecast = saveForecast([forecastRule()]);
    expect($forecast->snapshot['months'][0]['before']['expenses'])->toEqual(150)
        ->and($forecast->snapshot['months'][0]['warnings'])->toHaveCount(1)
        ->and($forecast->snapshot['months'][0]['warnings'][0]['reason'])->toBe('marked_extra');
});

test('changing forecast classification invalidates stale previews and persists through normal post edits', function () {
    $budget = forecastBudget('rent', 1000, 'expense');
    $payload = forecastPayload([forecastRule('rent')]);
    $this->putJson('/api/sparen/budget-items/rent', [
        'name' => $budget->name, 'group' => $budget->category->name,
        'estimated' => 1000, 'forecastMode' => 'extra',
    ])->assertOk();
    expect($budget->fresh()->forecast_mode)->toBe('extra')->and((float) $budget->fresh()->budget)->toBe(1000.0);
    $this->postJson('/api/sparen/forecasts', $payload)->assertConflict();
    $this->putJson('/api/sparen/budget-items/rent', [
        'name' => $budget->name, 'group' => $budget->category->name, 'estimated' => 1000,
    ])->assertOk();
    expect($budget->fresh()->forecast_mode)->toBe('extra');
});

test('old saved forecasts keep their original totals and calculation version', function () {
    forecastBudget();
    $old = saveForecast([forecastRule()]);
    $snapshot = $old->snapshot;
    $snapshot['version'] = 1;
    $old->update(['snapshot' => $snapshot]);
    forecastBudget('Klarna', 500, 'expense');
    $this->getJson("/api/sparen/forecasts/{$old->key}")->assertOk()
        ->assertJsonPath('snapshot.version', 1)
        ->assertJsonPath('snapshot.months.0.after.free', 3090);
    expect($old->fresh()->snapshot)->toEqual($snapshot);
});

test('unapplied forecasts can change add disable and remove rules without creating a copy', function () {
    forecastBudget();
    forecastBudget('rent', 500, 'expense');
    $forecast = saveForecast([forecastRule()]);
    $rules = [forecastRule('income', '2027-05', 'percent', 5), forecastRule('rent', '2027-05', 'delta', -50, 'r2')];
    $revision = fn () => app(ForecastService::class)->payload($forecast->fresh())['revision'];
    $this->putJson("/api/sparen/forecasts/{$forecast->key}", [...forecastPayload($rules), 'revision' => $revision()])
        ->assertOk()->assertJsonPath('id', $forecast->key)->assertJsonCount(2, 'rules')
        ->assertJsonPath('snapshot.months.0.after.free', 2700);
    $rules[0]['enabled'] = false;
    $this->putJson("/api/sparen/forecasts/{$forecast->key}", [...forecastPayload($rules), 'revision' => $revision()])
        ->assertOk()->assertJsonPath('rules.0.enabled', false)->assertJsonPath('snapshot.months.0.after.free', 2550);
    $this->putJson("/api/sparen/forecasts/{$forecast->key}", [...forecastPayload([$rules[1]]), 'revision' => $revision()])
        ->assertOk()->assertJsonCount(1, 'rules')->assertJsonPath('rules.0.id', 'r2');
    $this->putJson("/api/sparen/forecasts/{$forecast->key}", [...forecastPayload([]), 'revision' => $revision()])
        ->assertOk()->assertJsonCount(0, 'rules')->assertJsonPath('snapshot.months.0.after.free', 2500);
    expect(Forecast::query()->count())->toBe(1)->and(BudgetAmountChange::query()->count())->toBe(0);
});

test('applying seals a forecast including after undo', function () {
    forecastBudget();
    $forecast = saveForecast([forecastRule()]);
    app(ForecastService::class)->apply($forecast);
    $before = $forecast->fresh()->snapshot;
    $payload = [...forecastPayload([forecastRule('income', '2027-05', 'percent', 8)]), 'revision' => app(ForecastService::class)->payload($forecast->fresh())['revision']];
    $this->putJson("/api/sparen/forecasts/{$forecast->key}", $payload)->assertConflict();
    app(ForecastService::class)->revert($forecast, '2026-11');
    $payload['sourceHash'] = app(ForecastService::class)->source('2027-05')['sourceHash'];
    $payload['revision'] = app(ForecastService::class)->payload($forecast->fresh())['revision'];
    $this->putJson("/api/sparen/forecasts/{$forecast->key}", $payload)->assertConflict();
    expect($forecast->fresh()->snapshot)->toEqual($before);
});

test('another window cannot overwrite or apply an outdated draft revision', function () {
    forecastBudget();
    $forecast = saveForecast([forecastRule()]);
    $oldRevision = app(ForecastService::class)->payload($forecast)['revision'];
    $payload = [...forecastPayload([forecastRule('income', '2027-05', 'percent', 5)]), 'revision' => $oldRevision];
    $this->putJson("/api/sparen/forecasts/{$forecast->key}", $payload)->assertOk();
    $payload['name'] = 'Andere wijziging';
    $this->putJson("/api/sparen/forecasts/{$forecast->key}", $payload)->assertConflict();
    $this->postJson("/api/sparen/forecasts/{$forecast->key}/apply", ['revision' => $oldRevision])->assertConflict();
    expect($forecast->fresh()->name)->toBe('Mijn prognose')->and(BudgetAmountChange::query()->count())->toBe(0);
});

test('draft revision is stable across storage serialization and a follow-up update', function () {
    forecastBudget();
    $created = $this->postJson('/api/sparen/forecasts', forecastPayload([forecastRule()]))->assertCreated()->json();
    $this->getJson("/api/sparen/forecasts/{$created['id']}")->assertOk()->assertJsonPath('revision', $created['revision']);
    $this->putJson("/api/sparen/forecasts/{$created['id']}", [...forecastPayload([forecastRule()]), 'revision' => $created['revision'], 'name' => 'Verder gewerkt'])
        ->assertOk()->assertJsonPath('name', 'Verder gewerkt');
});

test('drafts retain their baseline and require an explicit refresh after budget changes', function () {
    $budget = forecastBudget();
    $forecast = saveForecast([forecastRule()]);
    expect($forecast->snapshot['source']['months'])->toHaveCount(24);
    $budget->update(['budget' => 4000]);
    $revision = app(ForecastService::class)->payload($forecast)['revision'];
    $payload = [...forecastPayload([forecastRule()]), 'revision' => $revision, 'sourceHash' => $forecast->source_hash];
    $this->putJson("/api/sparen/forecasts/{$forecast->key}", $payload)->assertConflict();
    expect($forecast->fresh()->snapshot['source']['months'][0]['items']['income']['amount'])->toEqual(3000);
    $payload['sourceHash'] = app(ForecastService::class)->source('2027-05')['sourceHash'];
    $this->putJson("/api/sparen/forecasts/{$forecast->key}", $payload)->assertOk()
        ->assertJsonPath('snapshot.months.0.after.income', 4120);
    expect(Forecast::query()->count())->toBe(1);
});

test('latest December benefit amount is used on every existing quarterly payment month when chosen', function () {
    $budget = forecastBudget('benefit', 0);
    foreach (ReportingPeriod::SPAREN_MONTH_IDS as $index => $month) {
        $amount = [2 => 300, 5 => 320, 8 => 350, 11 => 362.35][$index] ?? 0;
        BudgetMonthValue::query()->create(['budget_id' => $budget->id, 'month_id' => $month, 'year' => 2026, 'estimated' => $amount]);
    }
    $source = app(ForecastService::class)->source('2027-01');
    expect($source['items'][0]['latestFixed']['sourceMonth'])->toBe('2026-12')
        ->and($source['items'][0]['latestFixed']['amount'])->toBe(362.35)
        ->and($source['items'][0]['amountVariants'])->toHaveCount(4);
    $forecast = app(ForecastService::class)->save([
        ...forecastPayload([], '2027-01'),
        'settings' => [['itemId' => 'benefit', 'basis' => 'latest', 'included' => true, 'excludedMonths' => []]],
    ], 'Tester');
    expect($forecast->snapshot['months'][0]['after']['income'])->toEqual(0)
        ->and($forecast->snapshot['months'][2]['before']['income'])->toEqual(300)
        ->and($forecast->snapshot['months'][2]['after']['income'])->toEqual(362.35)
        ->and($forecast->snapshot['months'][5]['after']['income'])->toEqual(362.35)
        ->and($forecast->snapshot['months'][11]['after']['income'])->toEqual(362.35);
    app(ForecastService::class)->apply($forecast);
    expect((new BudgetProjection)->resolve($budget, '2028-06')['amount'])->toBe(362.35);
});

test('monthly profile is retained unless the latest fixed amount is explicitly selected', function () {
    $budget = forecastBudget('seasonal', 100, 'expense');
    BudgetMonthValue::query()->create(['budget_id' => $budget->id, 'month_id' => 'jun', 'year' => 2026, 'estimated' => 50]);
    BudgetMonthValue::query()->create(['budget_id' => $budget->id, 'month_id' => 'dec', 'year' => 2026, 'estimated' => 120]);
    $forecast = saveForecast([], '2027-01');
    expect($forecast->snapshot['months'][0]['after']['expenses'])->toEqual(100)
        ->and($forecast->snapshot['months'][5]['after']['expenses'])->toEqual(50)
        ->and($forecast->snapshot['months'][11]['after']['expenses'])->toEqual(120);
});

test('latest amount and a percentage rule compose correctly and persist as fixed amounts', function () {
    $budget = forecastBudget('salary', 3000);
    BudgetMonthValue::query()->create(['budget_id' => $budget->id, 'month_id' => 'dec', 'year' => 2026, 'estimated' => 3200]);
    $forecast = app(ForecastService::class)->save([
        ...forecastPayload([forecastRule('salary', '2027-01', 'percent', 2)], '2027-01'),
        'settings' => [['itemId' => 'salary', 'basis' => 'latest', 'included' => true, 'excludedMonths' => []]],
    ], 'Tester');
    expect($forecast->snapshot['months'][0]['after']['income'])->toEqual(3264)
        ->and($forecast->snapshot['specs'])->toHaveCount(2);
    app(ForecastService::class)->apply($forecast);
    expect((new BudgetProjection)->resolve($budget, '2029-03')['amount'])->toBe(3264.0);
    $this->getJson('/api/sparen/budget-items/salary/history')->assertOk()->assertJsonPath('1.description', 'Laatste vaste bedrag uit 2026-12');
});

test('explicit future monthly plans take precedence over a chosen latest historical amount', function () {
    $budget = forecastBudget('salary', 3000);
    foreach ([['dec', 2026, 3200], ['jun', 2027, 3500]] as [$month, $year, $amount]) {
        BudgetMonthValue::query()->create(['budget_id' => $budget->id, 'month_id' => $month, 'year' => $year, 'estimated' => $amount]);
    }
    $forecast = app(ForecastService::class)->save([
        ...forecastPayload([], '2027-01'),
        'settings' => [['itemId' => 'salary', 'basis' => 'latest', 'included' => true, 'excludedMonths' => []]],
    ], 'Tester');
    expect($forecast->snapshot['months'][0]['after']['income'])->toEqual(3200)
        ->and($forecast->snapshot['months'][5]['after']['income'])->toEqual(3500);
    app(ForecastService::class)->apply($forecast);
    expect((new BudgetProjection)->resolve($budget, '2027-06')['amount'])->toBe(3500.0);
});

test('forecast-only omissions show their effect and never create budget cancellation records', function () {
    forecastBudget();
    $expense = forecastBudget('rent', 1000, 'expense');
    $forecast = app(ForecastService::class)->save([
        ...forecastPayload([], '2027-01'), 'settings' => [
            ['itemId' => 'income', 'basis' => 'profile', 'included' => false, 'excludedMonths' => []],
            ['itemId' => 'rent', 'basis' => 'profile', 'included' => true, 'excludedMonths' => ['2027-01']],
        ],
    ], 'Tester');
    expect($forecast->snapshot['months'][0]['before']['free'])->toEqual(2000)
        ->and($forecast->snapshot['months'][0]['after']['free'])->toEqual(0)
        ->and($forecast->snapshot['months'][1]['after']['free'])->toEqual(-1000)
        ->and($forecast->snapshot['months'][0]['afterItems']['rent']['omitted'])->toBeTrue()
        ->and($forecast->snapshot['specs'])->toHaveCount(0)
        ->and((new BudgetProjection)->resolve($expense, '2027-01')['amount'])->toBe(1000.0);
});

test('latest source follows the most recent dated value rather than the closest calendar slot', function () {
    $budget = forecastBudget('salary', 3000);
    foreach ([['dec', 2025, 3100], ['nov', 2026, 3300]] as [$month, $year, $amount]) {
        BudgetMonthValue::query()->create(['budget_id' => $budget->id, 'month_id' => $month, 'year' => $year, 'estimated' => $amount]);
    }
    $source = app(ForecastService::class)->source('2027-01');
    expect($source['items'][0]['latestFixed']['sourceMonth'])->toBe('2026-11')
        ->and($source['items'][0]['latestFixed']['amount'])->toBe(3300.0);
});

test('invalid month omissions cannot be saved and assumption changes invalidate stale revisions', function () {
    forecastBudget();
    $forecast = saveForecast([forecastRule()]);
    $revision = app(ForecastService::class)->payload($forecast)['revision'];
    $payload = [...forecastPayload([forecastRule()]), 'revision' => $revision, 'settings' => [
        ['itemId' => 'income', 'basis' => 'profile', 'included' => false, 'excludedMonths' => ['2029-01']],
    ]];
    $this->putJson("/api/sparen/forecasts/{$forecast->key}", $payload)->assertUnprocessable();
    $payload['settings'][0]['excludedMonths'] = [];
    $this->putJson("/api/sparen/forecasts/{$forecast->key}", $payload)->assertOk();
    $this->putJson("/api/sparen/forecasts/{$forecast->key}", $payload)->assertConflict();
});

test('calculation filters never change the payment pattern or amounts of an enabled rule', function () {
    $budget = forecastBudget();
    $forecast = app(ForecastService::class)->save([
        ...forecastPayload([forecastRule()]), 'settings' => [
            ['itemId' => 'income', 'basis' => 'profile', 'included' => true, 'excludedMonths' => ['2027-05']],
        ],
    ], 'Tester');
    expect($forecast->snapshot['months'][0]['after']['income'])->toEqual(0)
        ->and($forecast->snapshot['months'][1]['after']['income'])->toEqual(3090);
    app(ForecastService::class)->apply($forecast);
    expect((new BudgetProjection)->resolve($budget, '2027-05')['amount'])->toBe(3090.0);
});
