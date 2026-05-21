<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class Transactions
{
    public static $expense = 'expense';
    public static $income = 'income';
    public static $saving = 'saving';

    /** 
     * Retrieves the list of transactions.
     *
     * @return array The list of transactions.
     */
    public static function list(string $searchTerm, string $type, int $perPage = 100, int $page = 1) : LengthAwarePaginator
    {

        if (Schema::hasTable('transactions')) {
            $query = Transaction::query();

            if (trim($searchTerm) !== '') {
                $query->where('description', 'like', '%' . $searchTerm . '%');
            }

            if (trim($type) !== '') {
                if (in_array($type, [self::$income, self::$expense, self::$saving], true)) {
                    $query->where('type', $type);
                }
                if ($type === 'uncategorized') {
                    $query->whereNull('type');
                }
            }

            $transactions = $query->orderByDesc('date')
                ->paginate($perPage, ['*'], 'page', $page)
                ->through(function (Transaction $transaction) {
                    return [
                        'id' => $transaction->id,
                        'amount' => (float) $transaction->amount,
                        'categoryId' => $transaction->category_id,
                        'budgetId' => $transaction->budget_id,
                        'type' => $transaction->type,
                        'description' => $transaction->description,
                        'date' => $transaction->date->format('d-m-Y'),
                    ];
                });
        }

        return $transactions;
    }
}

