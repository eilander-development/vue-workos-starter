<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class Transactions
{
    public function __construct(
        protected ReportingPeriod $reportingPeriod,
    ) {}

    public string $expense = 'expense';

    public string $income = 'income';

    public string $saving = 'saving';

    /**
     * Retrieves the list of transactions.
     *
     * @return array The list of transactions.
     */
    public function list(string $searchTerm, string $type, int $perPage = 100, int $page = 1): LengthAwarePaginator
    {
        if (! Schema::hasTable('transactions')) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $query = Transaction::query();
        $query->whereDate('date', '>=', $this->reportingPeriod->startOfCurrentMonthFromDay(20)->toDateString());

        if (trim($searchTerm) !== '') {
            $query->where('description', 'like', '%'.$searchTerm.'%');
        }

        if (trim($type) !== '') {
            if (in_array($type, [$this->income, $this->expense, $this->saving], true)) {
                $query->where('type', $type);
            }
            if ($type === 'uncategorized') {
                $query->whereNull('type');
            }
        }

        return $query->orderByDesc('date')
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(function (Transaction $transaction) {
                return [
                    'id' => $transaction->id,
                    'amount' => (float) $transaction->amount,
                    'categoryId' => $transaction->category_id,
                    'budgetId' => $transaction->budget_id,
                    'type' => $transaction->type,
                    'description' => $transaction->description,
                    'date' => $transaction->date?->format('d-m-Y') ?? '',
                ];
            });
    }
}
