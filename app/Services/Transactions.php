<?php

namespace App\Services;

use App\DTOs\TransactionDTO;
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
    public function list(
        string $searchTerm,
        string $type,
        ?int $budgetId = null,
        ?string $sourceType = null,
        int $perPage = 100,
        int $page = 1,
        bool $applyReportingPeriod = true,
    ): LengthAwarePaginator
    {
        if (! Schema::hasTable('transactions')) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $query = Transaction::query();
        if ($applyReportingPeriod) {
            $query->whereDate('date', '>=', $this->reportingPeriod->startOfCurrentMonthFromDay($this->reportingPeriod->configuredStartDay())->toDateString());
        }

        if (trim($searchTerm) !== '') {
            $query->where('description', 'like', '%'.$searchTerm.'%');
        }

        if (trim($type) !== '') {
            if (in_array($type, [$this->income, $this->expense, $this->saving], true)) {
                $query->where('type', $type);
            }
            if ($type === 'uncategorized') {
                $query->whereNull('category_id');
            }
        }
        if ($budgetId) {
            $query->where('budget_id', $budgetId);
        }
        if ($sourceType && in_array($sourceType, ['csv', 'api'], true)) {
            $query->where('source_type', $sourceType);
        }

        return $query->orderByDesc('date')
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(function (Transaction $transaction) {
                return TransactionDTO::fromModel($transaction)->toArray();
            });
    }
}
