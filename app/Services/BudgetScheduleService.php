<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetAmountChange;
use App\Models\BudgetMonthValue;
use App\Models\Forecast;
use Illuminate\Support\Facades\Schema;

class BudgetScheduleService
{
    public function __construct(private ReportingPeriod $reporting) {}

    public function revision(Budget $budget): string
    {
        return hash('sha256', json_encode([
            $budget->getAttributes(),
            BudgetMonthValue::query()->where('budget_id', $budget->id)->orderBy('id')->get()->toArray(),
            Schema::connection('catalog')->hasTable('budget_amount_changes')
                ? BudgetAmountChange::query()->where('budget_id', $budget->id)->orderBy('id')->get()->toArray() : [],
        ]));
    }

    /** All actually available years; viewing a projected schedule never writes it. */
    public function forItem(string $key): array
    {
        $budget = Budget::query()->where('key', $key)->firstOrFail();
        $currentYear = $this->reporting->defaultSparenMonth()['year'];
        $years = BudgetMonthValue::query()->distinct()->pluck('year')->map(fn ($year) => (int) $year);
        if (Schema::connection('catalog')->hasTable('budget_amount_changes')) {
            foreach (BudgetAmountChange::query()->get(['effective_month', 'end_month']) as $change) {
                $years->push((int) substr($change->effective_month, 0, 4));
                if ($change->end_month) {
                    $years->push((int) substr($change->end_month, 0, 4));
                }
            }
            // A twelve-month applied forecast may span two calendar years.
            foreach (Forecast::query()->whereNotNull('applied_at')->get(['snapshot']) as $forecast) {
                foreach ($forecast->snapshot['months'] ?? [] as $month) {
                    $years->push((int) substr($month['month'], 0, 4));
                }
            }
        }
        $years = $years->push($currentYear)->unique()->sort()->values();
        $projection = new BudgetProjection;
        $schedules = $years->map(function (int $year) use ($budget, $projection, $currentYear) {
            $months = [];
            foreach (SparenStateService::MONTHS as $monthId => $name) {
                $slot = array_search($monthId, ReportingPeriod::SPAREN_MONTH_IDS, true) + 1;
                $key = sprintf('%04d-%02d', $year, $slot);
                $cell = $projection->resolve($budget, $key);
                $months[] = ['month' => $key, 'monthId' => $monthId, 'monthName' => $name, 'year' => $year,
                    'amount' => $cell['amount'], 'entries' => $cell['entries'], 'sourceMonth' => $cell['sourceMonth'],
                    'origin' => $cell['datedChange'] ? 'forecast' : ($cell['sourceMonth'] === $key ? 'saved' : 'derived'),
                ];
            }

            return ['year' => $year, 'period' => $year < $currentYear ? 'past' : ($year > $currentYear ? 'future' : 'current'), 'months' => $months];
        });

        return ['currentYear' => $currentYear, 'revision' => $this->revision($budget), 'schedules' => $schedules];
    }
}
