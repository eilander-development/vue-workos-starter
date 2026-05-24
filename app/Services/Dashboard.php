<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

class Dashboard
{
    public function __construct(
        protected Categories $categoriesService,
        protected ReportingPeriod $reportingPeriod,
    ) {}

    public function latestTransactions(int $page = 1, int $perPage = 100): LengthAwarePaginator
    {
        return $this->periodTransactionsQuery()
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

    public function stats(): array
    {
        $transactions = $this->periodTransactionsQuery()->get();
        $income = $transactions->filter(fn (Transaction $t) => (float) $t->amount > 0)->sum(fn (Transaction $t) => (float) $t->amount);
        $expenses = $transactions->filter(fn (Transaction $t) => (float) $t->amount < 0)->sum(fn (Transaction $t) => abs((float) $t->amount));

        return [
            'income' => (float) $income,
            'expenses' => (float) $expenses,
            'left' => (float) ($income - $expenses),
            'budgets' => $transactions->pluck('budget_id')->filter()->unique()->count(),
        ];
    }

    public function monthlyExpensesBudgets(): array
    {
        return $this->aggregateBudgetAmounts('budget');
    }

    public function monthlyExpensesSpend(): array
    {
        return $this->aggregateBudgetAmounts('spend');
    }

    public function yearlyExpensesChartSeries(): array
    {
        $transactions = $this->periodTransactionsQuery()->get();

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

    private function aggregateBudgetAmounts(string $key): array
    {
        $startDate = $this->reportingPeriod->startDate();

        return collect($this->categoriesService->list('all', $startDate))
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

    private function periodTransactionsQuery()
    {
        $now = CarbonImmutable::now();

        return Transaction::query()
            ->whereYear('date', $now->year)
            ->whereMonth('date', $now->month);
    }
}
