<?php

namespace App\Services;

use App\Models\Category;
use Carbon\CarbonInterface;

class Categories
{
    public function list(string $filter = 'all', ?CarbonInterface $startDate = null): array
    {
        return Category::with(['budgets.transactions' => function ($query) use ($startDate) {
            if ($startDate !== null) {
                $query->whereDate('date', '>=', $startDate->toDateString());
            }
        }])
            ->when($filter !== 'all', fn ($query) => $query->where('type', $filter))
            ->orderBy('name')
            ->get()
            ->map(function (Category $category) {
                return $this->categoryFromModel($category);
            })
            ->keyBy('id')
            ->toArray();
    }

    public function stats(array $categories): array
    {
        $categoryCount = count($categories);
        $budgetCount = collect($categories)->sum(fn ($category) => count($category['budgets'] ?? []));
        $expenseCategories = collect($categories)->where('type', 'expense');
        $incomeCategories = collect($categories)->where('type', 'income');
        $savingCategories = collect($categories)->where('type', 'saving');
        $expenseBudget = (float) $expenseCategories->sum('budget');
        $expenseSpend = (float) $expenseCategories->sum('spend');
        $incomeBudget = (float) $incomeCategories->sum('budget');
        $incomeSpend = (float) $incomeCategories->sum('spend');
        $savingBudget = (float) $savingCategories->sum('budget');
        $savingSpend = (float) $savingCategories->sum('spend');

        return [
            'categoryCount' => $categoryCount,
            'budgetCount' => $budgetCount,
            'totalBudget' => $expenseBudget,
            'totalSpend' => $expenseSpend,
            'expenseBudget' => $expenseBudget,
            'expenseSpend' => $expenseSpend,
            'incomeBudget' => $incomeBudget,
            'incomeSpend' => $incomeSpend,
            'savingBudget' => $savingBudget,
            'savingSpend' => $savingSpend,
        ];
    }

    /**
     * Convert an Eloquent category model into the same array shape used by the application.
     */
    private function categoryFromModel(Category $category): array
    {
        $budgets = $category->budgets->map(function ($budget) {
            $isExpenseCategory = $budget->category?->type === 'expense';
            $spend = $isExpenseCategory
                ? $budget->transactions
                    ->filter(fn ($transaction) => (float) $transaction->amount < 0)
                    ->sum(fn ($transaction) => abs((float) $transaction->amount))
                : $budget->transactions
                    ->filter(fn ($transaction) => (float) $transaction->amount > 0)
                    ->sum(fn ($transaction) => (float) $transaction->amount);
            $remaining = (float) $budget->budget - $spend;
            $unpaid = $remaining > 0 ? $remaining : 0;
            $overdue = $remaining > 0 ? 0 : abs($remaining);

            return [
                'id' => $budget->id,
                'name' => $budget->name,
                'budget' => (float) $budget->budget,
                'spend' => $spend,
                'remaining' => $remaining,
                'unpaid' => $unpaid,
                'overdue' => $overdue,
            ];
        })->toArray();

        $budgetTypes = collect($category->budgets)
            ->flatMap(fn ($budget) => $budget->transactions->pluck('type'))
            ->filter()
            ->unique()
            ->values();

        $totalBudget = collect($budgets)->sum('budget');
        $totalSpend = collect($budgets)->sum('spend');
        $remaining = $totalBudget - $totalSpend;

        return [
            'id' => $category->id,
            'name' => $category->name,
            'category' => $category->name,
            'slug' => $category->slug,
            'icon' => $category->icon,
            'color' => $category->color,
            'type' => $category->type,
            'budgets' => $budgets,
            'budget' => $totalBudget,
            'spend' => $totalSpend,
            'remaining' => $remaining,
            'unpaid' => collect($budgets)->sum('unpaid'),
            'overdue' => collect($budgets)->sum('overdue'),
        ];
    }
}
