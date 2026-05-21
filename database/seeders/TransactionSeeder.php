<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $expenseCategory = Category::query()->where('type', 'expense')->with('budgets')->first();
        $incomeCategory = Category::query()->firstOrCreate(
            ['slug' => 'inkomsten'],
            ['name' => 'Inkomsten', 'type' => 'income', 'icon' => 'Euro', 'color' => 'green']
        );
        $savingCategory = Category::query()->firstOrCreate(
            ['slug' => 'sparen'],
            ['name' => 'Sparen', 'type' => 'saving', 'icon' => 'PiggyBank', 'color' => 'blue']
        );

        $expenseBudget = $expenseCategory?->budgets()->first();
        $incomeBudget = $incomeCategory->budgets()->firstOrCreate(
            ['name' => 'Salaris'],
            ['budget' => 3200]
        );
        $savingBudget = $savingCategory->budgets()->firstOrCreate(
            ['name' => 'Noodfonds'],
            ['budget' => 600]
        );

        $transactions = [
            ['amount' => -42.50, 'type' => 'expense', 'description' => 'Supermarkt', 'days_ago' => 2, 'category' => $expenseCategory, 'budget' => $expenseBudget],
            ['amount' => -18.90, 'type' => 'expense', 'description' => 'Lunch', 'days_ago' => 5, 'category' => $expenseCategory, 'budget' => $expenseBudget],
            ['amount' => -79.00, 'type' => 'expense', 'description' => 'Tankstation', 'days_ago' => 8, 'category' => $expenseCategory, 'budget' => $expenseBudget],
            ['amount' => 3150.00, 'type' => 'income', 'description' => 'Salaris mei', 'days_ago' => 1, 'category' => $incomeCategory, 'budget' => $incomeBudget],
            ['amount' => 220.00, 'type' => 'income', 'description' => 'Freelance klus', 'days_ago' => 6, 'category' => $incomeCategory, 'budget' => $incomeBudget],
            ['amount' => 100.00, 'type' => 'saving', 'description' => 'Inleg spaarrekening', 'days_ago' => 3, 'category' => $savingCategory, 'budget' => $savingBudget],
            ['amount' => 150.00, 'type' => 'saving', 'description' => 'Extra sparen', 'days_ago' => 10, 'category' => $savingCategory, 'budget' => $savingBudget],
            ['amount' => 53.30, 'type' => null, 'description' => 'Nog te categoriseren +', 'days_ago' => 4, 'category' => null, 'budget' => null],
            ['amount' => -3.30, 'type' => null, 'description' => 'Nog te categoriseren -', 'days_ago' => 7, 'category' => null, 'budget' => null],
        ];

        foreach ($transactions as $transaction) {
            if (($transaction['type'] !== null) && (!$transaction['category'] || !$transaction['budget'])) {
                continue;
            }

            $date = now()->subDays($transaction['days_ago'])->format('Y-m-d');
            $attributes = [
                'amount' => $transaction['amount'],
                'category_id' => $transaction['category']?->id,
                'budget_id' => $transaction['budget']?->id,
                'type' => $transaction['type'],
                'description' => $transaction['description'],
                'date' => $date,
                'icon' => $transaction['category']?->icon,
                'color' => $transaction['category']?->color,
            ];

            Transaction::updateOrCreate(
                [
                    'description' => $transaction['description'],
                    'date' => $date,
                    'amount' => $transaction['amount'],
                ],
                $attributes
            );
        }
    }
}
