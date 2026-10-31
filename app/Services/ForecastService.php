<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetAmountChange;
use App\Models\BudgetMonthValue;
use App\Models\Category;
use App\Models\Forecast;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ForecastService
{
    public function __construct(private ReportingPeriod $reporting, private ForecastCalculator $calculator) {}

    public function firstFutureMonth(): string
    {
        $current = $this->reporting->defaultSparenMonth();
        $number = array_search($current['monthId'], ReportingPeriod::SPAREN_MONTH_IDS, true) + 1;

        return CarbonImmutable::create($current['year'], $number, 1)->addMonth()->format('Y-m');
    }

    public function source(string $start): array
    {
        $this->assertFuture($start);
        $projection = new BudgetProjection;
        $policy = new ForecastExpensePolicy;
        $budgets = Budget::query()->with('category')->orderBy('id')->get();
        $items = $budgets->map(fn ($budget) => [
            'id' => $budget->key, 'name' => $budget->name, 'group' => $budget->category?->name,
            'forecastMode' => $budget->forecast_mode ?? 'auto',
            ...$this->amountHistory($budget, $start, $projection, $policy),
            'type' => match ($budget->category?->type) {
                'income' => 'inkomsten', 'saving' => 'sparen', default => 'uitgaven',
            },
        ])->all();
        $months = [];
        // The second cycle supplies all annual slots even for a rule starting in month 12.
        $date = CarbonImmutable::createFromFormat('!Y-m', $start);
        for ($i = 0; $i < 24; $i++) {
            $key = $date->addMonths($i)->format('Y-m');
            $cells = [];
            foreach ($budgets as $budget) {
                $resolved = $projection->resolve($budget, $key);
                $cells[$budget->key] = $policy->cell($budget, $resolved, $key);
                $cells[$budget->key]['sourceMonth'] = $resolved['sourceMonth'];
                $cells[$budget->key]['datedChange'] = $resolved['datedChange'];
            }
            $months[] = ['month' => $key, 'items' => $cells];
        }

        return [
            'version' => 3, 'startMonth' => $start, 'minMonth' => $this->firstFutureMonth(), 'items' => $items,
            'months' => $months, 'sourceHash' => $this->sourceHash(),
            'boundaries' => $budgets->mapWithKeys(fn ($budget) => [$budget->key => $projection->boundaries($budget->id)])->all(),
        ];
    }

    public function save(array $data, ?string $actor, ?Forecast $existing = null): Forecast
    {
        if (trim($data['name']) === '') {
            throw ValidationException::withMessages(['name' => 'Geef de prognose een naam.']);
        }

        return DB::connection('catalog')->transaction(function () use ($data, $actor, $existing) {
            if ($existing) {
                $existing = Forecast::query()->whereKey($existing->id)->lockForUpdate()->firstOrFail();
                abort_if($existing->applied_at, 409, 'Deze prognose is doorgevoerd en staat op slot.');
                $this->assertRevision($existing, $data['revision']);
            }
            $this->lockSources();
            $source = $this->source($data['startMonth']);
            $this->assertHash($data['sourceHash'], $source['sourceHash']);
            $last = $source['months'][11]['month'];
            $seen = [];
            foreach ($data['rules'] as $rule) {
                if ($rule['effectiveMonth'] < $data['startMonth'] || $rule['effectiveMonth'] > $last) {
                    throw ValidationException::withMessages(['rules' => 'Iedere ingangsmaand moet binnen de twaalf maanden van de prognose liggen.']);
                }
                if (! ($rule['enabled'] ?? true)) {
                    continue;
                }
                $key = $rule['itemId'].'/'.$rule['effectiveMonth'];
                if (isset($seen[$key])) {
                    throw ValidationException::withMessages(['rules' => 'Gebruik per post maximaal één wijziging in dezelfde ingangsmaand.']);
                }
                $seen[$key] = true;
            }
            if (($data['copiedFrom'] ?? null) && ! Forecast::query()->where('key', $data['copiedFrom'])->exists()) {
                throw ValidationException::withMessages(['copiedFrom' => 'De oorspronkelijke prognose bestaat niet meer.']);
            }

            $snapshot = $this->calculator->calculate($source, $data['rules'], $data['settings'] ?? []);
            $snapshot['source'] = $source;
            $values = [
                'name' => trim($data['name']), 'notes' => $data['notes'] ?? null,
                'start_month' => $data['startMonth'], 'source_hash' => $source['sourceHash'],
                'rules' => $data['rules'], 'snapshot' => $snapshot,
            ];
            if ($existing) {
                $existing->update($values);

                return $existing->fresh();
            }

            return Forecast::query()->create([
                ...$values, 'key' => (string) Str::uuid(), 'copied_from' => $data['copiedFrom'] ?? null, 'created_by' => $actor,
            ])->fresh();
        });
    }

    public function apply(Forecast $forecast, ?string $revision = null): Forecast
    {
        return DB::connection('catalog')->transaction(function () use ($forecast, $revision) {
            $forecast = Forecast::query()->whereKey($forecast->id)->lockForUpdate()->firstOrFail();
            abort_if($forecast->applied_at, 409, 'Deze prognose is al doorgevoerd.');
            if ($revision !== null) {
                $this->assertRevision($forecast, $revision);
            }
            $this->assertFuture($forecast->start_month);
            $this->lockSources();
            $this->assertHash($forecast->source_hash, $this->sourceHash());
            $projection = new BudgetProjection;
            $count = 0;
            foreach ($forecast->snapshot['specs'] as $spec) {
                if (! $spec['firstPayment']) {
                    continue;
                }
                $budget = Budget::query()->where('key', $spec['itemId'])->firstOrFail();
                BudgetAmountChange::query()->create([
                    'key' => (string) Str::uuid(), 'forecast_key' => $forecast->key, 'rule_id' => $spec['ruleId'],
                    'budget_id' => $budget->id, 'effective_month' => $spec['effectiveMonth'],
                    'end_month' => $spec['endMonth'], 'before_amounts' => $spec['beforeAmounts'],
                    'amounts' => $spec['amounts'], 'scheduled_months' => $spec['scheduledMonths'],
                    'known_values' => $projection->knownValues($budget->id),
                ]);
                $count++;
            }
            abort_unless($count, 422, 'Deze prognose bevat geen wijzigingen om door te voeren.');
            $forecast->update(['applied_at' => now()]);

            return $forecast->fresh();
        });
    }

    public function revert(Forecast $forecast, string $from): Forecast
    {
        $this->assertFuture($from);

        return DB::connection('catalog')->transaction(function () use ($forecast, $from) {
            $forecast = Forecast::query()->whereKey($forecast->id)->lockForUpdate()->firstOrFail();
            abort_unless($forecast->applied_at && ! $forecast->reverted_at, 409, 'Deze prognose kan niet worden teruggedraaid.');
            $this->lockSources();
            $changes = BudgetAmountChange::query()->where('forecast_key', $forecast->key)->get();
            $budgetIds = $changes->pluck('budget_id');
            $later = BudgetAmountChange::query()->whereIn('budget_id', $budgetIds)
                ->where('forecast_key', '!=', $forecast->key)->where('id', '>', $changes->min('id'))
                ->where(fn ($q) => $q->whereNull('end_month')->orWhere('end_month', '>', $from))->exists();
            abort_if($later, 409, 'Er zijn latere wijzigingen op deze posten. Maak een nieuwe prognose als correctie, zodat die wijzigingen behouden blijven.');

            $projection = new BudgetProjection;
            foreach ($changes as $change) {
                abort_if($projection->knownValues($change->budget_id) != $change->known_values, 409,
                    'Een post is inmiddels handmatig aangepast. Maak een nieuwe prognose als correctie.');
                if (! $change->end_month || $change->end_month > $from) {
                    $change->update(['end_month' => max($from, $change->effective_month)]);
                }
            }
            $forecast->update(['reverted_at' => now(), 'reverted_from' => $from]);

            return $forecast->fresh();
        });
    }

    public function payload(Forecast $forecast, bool $detail = true): array
    {
        $appliedRules = BudgetAmountChange::query()->where('forecast_key', $forecast->key)
            ->where(fn ($q) => $q->whereNull('end_month')->orWhereColumn('end_month', '>', 'effective_month'))
            ->pluck('rule_id')->all();

        return [
            'id' => $forecast->key, 'name' => $forecast->name, 'notes' => $forecast->notes,
            'startMonth' => $forecast->start_month, 'createdAt' => $forecast->created_at->toIso8601String(),
            'updatedAt' => $forecast->updated_at->toIso8601String(), 'revision' => $this->revision($forecast),
            'sourceHash' => $forecast->source_hash,
            'createdBy' => $forecast->created_by, 'copiedFrom' => $forecast->copied_from,
            'appliedAt' => $forecast->applied_at?->toIso8601String(), 'revertedAt' => $forecast->reverted_at?->toIso8601String(),
            'revertedFrom' => $forecast->reverted_from, 'appliedRuleIds' => $appliedRules,
            ...($detail ? ['rules' => $forecast->rules, 'snapshot' => $forecast->snapshot] : []),
        ];
    }

    public function history(string $itemKey): array
    {
        $budget = Budget::query()->where('key', $itemKey)->firstOrFail();
        $forecasts = Forecast::query()->get()->keyBy('key');

        return BudgetAmountChange::query()->where('budget_id', $budget->id)->orderByDesc('id')->get()
            ->map(function ($change) use ($forecasts) {
                $forecast = $forecasts->get($change->forecast_key);
                $rule = collect($forecast?->rules ?? [])->firstWhere('id', $change->rule_id);
                $spec = collect($forecast?->snapshot['specs'] ?? [])->firstWhere('ruleId', $change->rule_id);

                return [
                    'id' => $change->key, 'forecastId' => $change->forecast_key, 'forecastName' => $forecast?->name,
                    'effectiveMonth' => $change->effective_month, 'endMonth' => $change->end_month,
                    'beforeAmounts' => $change->before_amounts, 'amounts' => $change->amounts,
                    'scheduledMonths' => $change->scheduled_months, 'rule' => $rule,
                    'description' => $spec['label'] ?? null,
                    'createdAt' => $change->created_at->toIso8601String(), 'createdBy' => $forecast?->created_by,
                    'notes' => $forecast?->notes, 'revertedAt' => $forecast?->reverted_at?->toIso8601String(),
                    'revertedFrom' => $forecast?->reverted_from,
                ];
            })->all();
    }

    private function amountHistory(Budget $budget, string $start, BudgetProjection $projection, ForecastExpensePolicy $policy): array
    {
        $anchor = CarbonImmutable::createFromFormat('!Y-m', $start);
        $latest = null;
        $amounts = [];
        for ($i = 1; $i <= 12; $i++) {
            $month = $anchor->subMonths($i)->format('Y-m');
            $resolved = $projection->resolve($budget, $month);
            $cell = $policy->cell($budget, $resolved, $month);
            if (! $cell['scheduled']) {
                continue;
            }
            $amounts[] = $cell['amount'];
            $candidate = ['amount' => $cell['amount'], 'sourceMonth' => $resolved['sourceMonth']];
            if ($latest === null || ($candidate['sourceMonth'] ?? '') > ($latest['sourceMonth'] ?? '')) {
                $latest = $candidate;
            }
        }

        return ['latestFixed' => $latest, 'amountVariants' => array_values(array_unique($amounts))];
    }

    private function assertFuture(string $month): void
    {
        if ($month < $this->firstFutureMonth()) {
            throw ValidationException::withMessages(['startMonth' => 'Kies een toekomstige begrotingsmaand. De huidige en afgelopen maanden zijn uitgesloten.']);
        }
    }

    private function assertHash(string $expected, string $actual): void
    {
        abort_unless(hash_equals($expected, $actual), 409,
            'De begroting is ondertussen gewijzigd. Haal de actuele gegevens opnieuw op en controleer de prognose.');
    }

    private function revision(Forecast $forecast): string
    {
        // Explicit field order remains stable after MySQL normalizes JSON object keys.
        $rules = array_map(fn ($rule) => [
            $rule['id'], $rule['itemId'], $rule['effectiveMonth'], $rule['operation'],
            (string) $rule['value'], (bool) $rule['enabled'],
        ], $forecast->rules);

        return hash('sha256', json_encode([
            $forecast->key, $forecast->name, $forecast->notes, $forecast->start_month,
            $forecast->source_hash, $rules, array_map(fn ($setting) => [
                $setting['itemId'], $setting['basis'], $setting['included'], $setting['excludedMonths'] ?? [],
            ], $forecast->snapshot['settings'] ?? []), $forecast->applied_at?->toIso8601String(),
            $forecast->reverted_at?->toIso8601String(),
        ]));
    }

    private function assertRevision(Forecast $forecast, string $revision): void
    {
        abort_unless(hash_equals($this->revision($forecast), $revision), 409,
            'Deze prognose is ondertussen in een ander venster gewijzigd. Open haar opnieuw voordat je opslaat of doorvoert.');
    }

    private function sourceHash(): string
    {
        $columns = ['id', 'key', 'name', 'category_id', 'budget'];
        if (Schema::connection('catalog')->hasColumn('budgets', 'forecast_mode')) {
            $columns[] = 'forecast_mode';
        }

        return hash('sha256', json_encode([
            'structural-v3',
            Budget::query()->orderBy('id')->get($columns)->toArray(),
            Category::query()->orderBy('id')->get(['id', 'name', 'type'])->toArray(),
            BudgetMonthValue::query()->orderBy('id')->get(['budget_id', 'month_id', 'year', 'estimated', 'entries'])->toArray(),
            BudgetAmountChange::query()->orderBy('id')->get()->toArray(),
        ]));
    }

    private function lockSources(): void
    {
        Budget::query()->orderBy('id')->lockForUpdate()->get();
        BudgetMonthValue::query()->orderBy('id')->lockForUpdate()->get();
        BudgetAmountChange::query()->orderBy('id')->lockForUpdate()->get();
    }
}
