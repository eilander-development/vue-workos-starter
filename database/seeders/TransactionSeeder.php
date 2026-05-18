<?php

namespace Database\Seeders;

use App\Models\Transaction;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $transactions = [
            [
                'id' => 1,
                'amount' => -23.30,
                'category_id' => 1,
                'budget_id' => 1,
                'type' => 'expense',
                'description' => 'Pizza Palace',
                'date' => now()->format('Y-m-d'),
                'icon' => 'Salad',
                'color' => 'red',
            ],
            [
                'id' => 2,
                'amount' => 53.30,
                'category_id' => 2,
                'budget_id' => 1,
                'type' => 'income',
                'description' => 'Belastingdienst',
                'date' => now()->format('Y-m-d'),
                'icon' => 'Car',
                'color' => 'yellow',
            ],
            [
                'id' => 3,
                'amount' => 53.30,
                'category_id' => null,
                'budget_id' => null,
                'type' => null,
                'description' => 'Belastingdienst',
                'date' => now()->format('Y-m-d'),
                'icon' => null,
                'color' => null,
            ],
            [
                'id' => 4,
                'amount' => -3.30,
                'category_id' => null,
                'budget_id' => null,
                'type' => null,
                'description' => 'Belastingdienst',
                'date' => now()->format('Y-m-d'),
                'icon' => null,
                'color' => null,
            ],
        ];

        foreach ($transactions as $transaction) {
            Transaction::updateOrCreate(
                ['id' => $transaction['id']],
                $transaction
            );
        }
    }
}
