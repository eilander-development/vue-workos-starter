<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ImportRule;
use Illuminate\Database\Seeder;

class ImportRuleSeeder extends Seeder
{
    public function run(): void
    {
        $expenseCategory = Category::query()->where('type', 'expense')->with('budgets')->first();
        $incomeCategory = Category::query()->where('type', 'income')->with('budgets')->first();

        if ($expenseCategory && $expenseCategory->budgets->isNotEmpty()) {
            ImportRule::updateOrCreate(
                ['type' => 'description', 'match_value' => 'Albert Heijn'],
                ['category_id' => $expenseCategory->id, 'budget_id' => $expenseCategory->budgets->first()->id]
            );
        }

        if ($incomeCategory && $incomeCategory->budgets->isNotEmpty()) {
            ImportRule::updateOrCreate(
                ['type' => 'iban', 'match_value' => 'NL22RABO0987654321'],
                ['category_id' => $incomeCategory->id, 'budget_id' => $incomeCategory->budgets->first()->id]
            );
        }
    }
}

