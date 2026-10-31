<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetAmountChange;
use App\Models\BudgetMonthValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/** Resolves concrete amounts, including annual patterns and dated fixed changes. */
class BudgetProjection
{
    private Collection $values;

    private Collection $changes;

    private Collection $budgetAmounts;

    public function __construct()
    {
        $this->values = BudgetMonthValue::query()->orderBy('year')->orderBy('id')->get()->groupBy('budget_id');
        $this->changes = Schema::connection('catalog')->hasTable('budget_amount_changes')
            ? BudgetAmountChange::query()->orderBy('effective_month')->orderBy('id')->get()->groupBy('budget_id')
            : collect();
        $this->budgetAmounts = Budget::query()->pluck('budget', 'id');
    }

    public function knownValues(int $budgetId): array
    {
        return ['@base' => (string) $this->budgetAmounts->get($budgetId), ...$this->values->get($budgetId, collect())->mapWithKeys(
            fn ($value) => [$this->valueMonth($value) => $this->fingerprint($value)]
        )->all()];
    }

    public function boundaries(int $budgetId): array
    {
        return $this->changes->get($budgetId, collect())
            ->filter(fn ($change) => ! $change->end_month || $change->end_month > $change->effective_month)
            ->flatMap(fn ($change) => array_filter([$change->effective_month, $change->end_month]))
            ->unique()->sort()->values()->all();
    }

    public function resolve(Budget $budget, string $month): array
    {
        $year = (int) substr($month, 0, 4);
        $slot = (int) substr($month, 5, 2);
        $monthId = ReportingPeriod::SPAREN_MONTH_IDS[$slot - 1];
        $value = $this->values->get($budget->id, collect())
            ->filter(fn ($row) => $row->month_id === $monthId && $row->year <= $year)->last();
        $entries = $value?->entries ?? [];
        $amount = $entries !== [] ? collect($entries)->sum('amount') : (float) ($value?->estimated ?? $budget->budget);
        $result = [
            'amount' => round($amount, 2), 'entries' => $entries, 'scheduled' => $amount > 0,
            'sourceMonth' => $value ? $this->valueMonth($value) : null, 'datedChange' => false,
        ];

        foreach ($this->changes->get($budget->id, collect()) as $change) {
            if ($change->effective_month > $month || ($change->end_month && $month >= $change->end_month)) {
                continue;
            }

            // An edit made outside this forecast takes priority. Fingerprints also work
            // when SQLite timestamps have only second precision or backups are restored.
            $known = $change->known_values;
            if (! $value && ($known['@base'] ?? null) !== (string) $budget->budget) {
                continue;
            }
            if ($value && ($known[$this->valueMonth($value)] ?? null) !== $this->fingerprint($value)) {
                continue;
            }
            $exactKey = $month;
            if (! $value && isset($known[$exactKey])) {
                continue;
            }

            // A concrete later-year amount may already represent another plan.
            // Apply a revision only to its expected predecessor, never over it.
            if (round($result['amount'], 2) !== (float) ($change->before_amounts[$slot] ?? 0)) {
                continue;
            }

            $result = [
                'amount' => (float) ($change->amounts[$slot] ?? 0),
                'entries' => [],
                'scheduled' => in_array($slot, $change->scheduled_months, true),
                'sourceMonth' => $change->effective_month, 'datedChange' => true,
            ];
        }

        return $result;
    }

    private function valueMonth(BudgetMonthValue $value): string
    {
        return sprintf('%04d-%02d', $value->year, array_search($value->month_id, ReportingPeriod::SPAREN_MONTH_IDS, true) + 1);
    }

    private function fingerprint(BudgetMonthValue $value): string
    {
        return hash('sha256', json_encode([(string) $value->estimated, $value->entries]));
    }
}
