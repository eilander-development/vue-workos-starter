<?php

namespace App\Services;

use App\Models\Transaction;
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
    public static function list(string $searchTerm, string $type) : array
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

            $transactions = $query->get()->map(function (Transaction $transaction) {
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
        } else {
            $transactions = collect([
                [
                    'id' => 1,
                    'amount' => -23.30,
                    'categoryId' => 1,
                    'budgetId' => 1,
                    'type' => self::$expense,
                    'description' => 'Pizza Palace',
                    'date' => now()->format('d-m-Y'),
                ],
                [
                    'id' => 2,
                    'amount' => +53.30,
                    'categoryId' => 2,
                    'budgetId' => 1,
                    'type' => self::$income,
                    'description' => 'Belastingdienst',
                    'date' => now()->format('d-m-Y'),
                ],
                [
                    'id' => 3,
                    'amount' => +53.30,
                    'categoryId' => null,
                    'budgetId' => null,
                    'type' => null,
                    'description' => 'Belastingdienst',
                    'date' => now()->format('d-m-Y'),
                ],
                [
                    'id' => 4,
                    'amount' => -3.30,
                    'categoryId' => null,
                    'budgetId' => null,
                    'type' => null,
                    'description' => 'Belastingdienst',
                    'date' => now()->format('d-m-Y'),
                ]
            ]);

            if (trim($searchTerm) !== '') {
                $transactions = $transactions->filter(function ($transaction) use ($searchTerm) {
                    return str_contains(strtolower($transaction['description']), strtolower($searchTerm));
                });
            }

            if (trim($type) !== '') {
                if (in_array($type, [self::$income, self::$expense, self::$saving])) {
                    $transactions = $transactions->filter(function ($transaction) use ($type) {
                        return $transaction['type'] === $type;
                    });
                }
                if ($type === 'uncategorized') {
                    $transactions = $transactions->filter(function ($transaction) {
                        return $transaction['type'] === null;
                    });
                }
            }
        }

        return $transactions->toArray();
    }
}

