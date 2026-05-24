<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\DynamicBudget;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

class Dashboard
{
    public function __construct(
        protected Categories $categoriesService,
        protected ReportingPeriod $reportingPeriod,
    ) {}

    public function latestTransactions(int $page = 1, int $perPage = 100, ?CarbonImmutable $start = null, ?CarbonImmutable $end = null): LengthAwarePaginator
    {
        return $this->periodTransactionsQuery($start, $end)
            ->orderByDesc('date')
            ->paginate($perPage, ['*'], 'transactions_page', $page)
            ->through(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'amount' => (float) $transaction->amount,
                'categoryId' => $transaction->category_id,
                'budgetId' => $transaction->budget_id,
                'description' => $transaction->description,
                'date' => $transaction->date?->format('d-m-Y') ?? '',
                'type' => $transaction->type,
            ]);
    }

    public function stats(?CarbonImmutable $start = null, ?CarbonImmutable $end = null, ?float $currentBalanceOverride = null, ?string $month = null): array
    {
        $transactions = $this->periodTransactionsQuery($start, $end)->get();
        $income = $transactions->filter(fn (Transaction $t) => (float) $t->amount > 0)->sum(fn (Transaction $t) => (float) $t->amount);
        $expenses = $transactions->filter(fn (Transaction $t) => (float) $t->amount < 0)->sum(fn (Transaction $t) => abs((float) $t->amount));
        $currentBalance = $currentBalanceOverride ?? (float) Transaction::query()->sum('amount');
        $remainingBudgetCategories = collect([
            ...array_values($this->categoriesService->list('expense', $start, $end)),
            ...array_values($this->categoriesService->list('saving', $start, $end)),
        ])
            ->map(function (array $category) {
                $budgets = collect($category['budgets'] ?? [])
                    ->filter(fn (array $budget) => (float) ($budget['budget'] ?? 0) > 0)
                    ->map(function (array $budget) {
                        $remainingRaw = (float) ($budget['remaining'] ?? 0);
                        return [
                            'id' => $budget['id'] ?? null,
                            'name' => $budget['name'] ?? 'Onbekend budget',
                            'budget' => (float) ($budget['budget'] ?? 0),
                            'spend' => (float) ($budget['spend'] ?? 0),
                            'remaining' => $remainingRaw,
                            'toPay' => max(0, $remainingRaw),
                            'overspent' => max(0, abs(min(0, $remainingRaw))),
                        ];
                    })
                    ->values();

                return [
                    'id' => $category['id'] ?? null,
                    'name' => $category['category'] ?? ($category['name'] ?? 'Onbekende categorie'),
                    'budgets' => $budgets->all(),
                    'budgeted' => (float) $budgets->sum('budget'),
                    'spent' => (float) $budgets->sum('spend'),
                    'remaining' => (float) $budgets->sum('remaining'),
                    'toPay' => (float) $budgets->sum('toPay'),
                    'overspent' => (float) $budgets->sum('overspent'),
                ];
            })
            ->filter(fn (array $category) => count($category['budgets']) > 0)
            ->values();

        $dynamicRows = DynamicBudget::query()
            ->when($month, fn ($q) => $q->where('month', $month))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (DynamicBudget $row) {
                $budget = (float) $row->budget;
                $paid = (float) $row->paid;
                $remaining = $budget - $paid;

                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'budget' => $budget,
                    'spend' => $paid,
                    'remaining' => $remaining,
                    'toPay' => max(0, $remaining),
                    'overspent' => max(0, abs(min(0, $remaining))),
                ];
            })
            ->values();

        if ($dynamicRows->isNotEmpty()) {
            $remainingBudgetCategories->prepend([
                'id' => 'dynamic',
                'name' => 'Dynamisch budget',
                'budgets' => $dynamicRows->all(),
                'budgeted' => (float) $dynamicRows->sum('budget'),
                'spent' => (float) $dynamicRows->sum('spend'),
                'remaining' => (float) $dynamicRows->sum('remaining'),
                'toPay' => (float) $dynamicRows->sum('toPay'),
                'overspent' => (float) $dynamicRows->sum('overspent'),
            ]);
        }

        $budgetedTotal = (float) $remainingBudgetCategories->sum('budgeted');
        $spentTotal = (float) $remainingBudgetCategories->sum('spent');
        $toPayBudgets = (float) $remainingBudgetCategories->sum('toPay');
        $overspentBudgets = (float) $remainingBudgetCategories->sum('overspent');
        $remainingBudgets = $toPayBudgets;
        $afterBudgets = $currentBalance - $toPayBudgets;

        return [
            'income' => (float) $income,
            'expenses' => (float) $expenses,
            'left' => (float) ($income - $expenses),
            'budgets' => $transactions->pluck('budget_id')->filter()->unique()->count(),
            'currentBalance' => $currentBalance,
            'budgetedTotal' => $budgetedTotal,
            'spentTotal' => $spentTotal,
            'toPayBudgets' => $toPayBudgets,
            'overspentBudgets' => $overspentBudgets,
            'remainingBudgets' => $remainingBudgets,
            'afterBudgets' => $afterBudgets,
            'hasBudgetCoverage' => $afterBudgets >= 0,
            'remainingBudgetCategories' => $remainingBudgetCategories->all(),
            'dynamicBudgets' => $dynamicRows->all(),
        ];
    }

    public function monthlyExpensesBudgets(?CarbonImmutable $start = null, ?CarbonImmutable $end = null): array
    {
        return $this->aggregateBudgetAmounts('budget', $start, $end);
    }

    public function monthlyExpensesSpend(?CarbonImmutable $start = null, ?CarbonImmutable $end = null): array
    {
        return $this->aggregateBudgetAmounts('spend', $start, $end);
    }

    public function yearlyExpensesChartSeries(?CarbonImmutable $start = null, ?CarbonImmutable $end = null): array
    {
        $transactions = $this->periodTransactionsQuery($start, $end)->get();

        $incomeByMonth = [];
        $expensesByMonth = [];
        $monthLabels = ['Jan', 'Feb', 'Mrt', 'Apr', 'Mei', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'];

        for ($month = 1; $month <= 12; $month++) {
            $monthlyTransactions = $transactions->filter(
                fn (Transaction $transaction) => (int) $transaction->date?->format('n') === $month
            );

            $income = $monthlyTransactions
                ->filter(fn (Transaction $transaction) => (float) $transaction->amount > 0)
                ->sum(fn (Transaction $transaction) => (float) $transaction->amount);

            $expenses = $monthlyTransactions
                ->filter(fn (Transaction $transaction) => (float) $transaction->amount < 0)
                ->sum(fn (Transaction $transaction) => abs((float) $transaction->amount));

            $incomeByMonth[] = [
                'x' => $monthLabels[$month - 1],
                'y' => (float) $income,
            ];

            $expensesByMonth[] = [
                'x' => $monthLabels[$month - 1],
                'y' => (float) $expenses,
            ];
        }

        return [
            ['name' => 'Income', 'color' => 'oklch(50.8% 0.118 165.612)', 'data' => $incomeByMonth],
            ['name' => 'Expenses', 'color' => 'oklch(44.4% 0.177 26.899)', 'data' => $expensesByMonth],
        ];
    }

    public function yearlyExpensesChartSeriesForYear(int $year): array
    {
        $start = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $end = CarbonImmutable::create($year, 12, 31)->endOfDay();

        return $this->yearlyExpensesChartSeries($start, $end);
    }

    private function aggregateBudgetAmounts(string $key, ?CarbonImmutable $start = null, ?CarbonImmutable $end = null): array
    {
        $startDate = $start ?? $this->reportingPeriod->startDate();

        return collect($this->categoriesService->list('all', $startDate, $end))
            ->flatMap(function (array $category) use ($key) {
                return collect($category['budgets'] ?? [])->map(fn (array $budget) => [
                    'categoryId' => (int) $category['id'],
                    'budgetId' => (int) $budget['id'],
                    'amount' => (float) ($budget[$key] ?? 0),
                ]);
            })
            ->filter(fn (array $row) => $row['budgetId'] > 0)
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    private function periodTransactionsQuery(?CarbonImmutable $start = null, ?CarbonImmutable $end = null)
    {
        $startDate = ($start ?? CarbonImmutable::now()->startOfMonth())->startOfDay();
        $endDate = ($end ?? CarbonImmutable::now()->endOfMonth())->endOfDay();

        return Transaction::query()
            ->where('date', '>=', $startDate->toDateTimeString())
            ->where('date', '<=', $endDate->toDateTimeString());
    }
}
