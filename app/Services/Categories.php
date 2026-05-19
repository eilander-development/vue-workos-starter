<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\Schema;

class Categories
{
    private static $cachedCategories = null;

    /** 
     * Retrieves the list of categories.
     *
     * @return array The list of categories.
     */
    public static function list() : array
    {
        if (self::$cachedCategories) {
            return self::$cachedCategories;
        }

        return Category::with(['budgets.transactions'])
            ->orderBy('name')
            ->get()
            ->map(function (Category $category) {
                return self::categoryFromModel($category);
            })
            ->keyBy('id')
            ->toArray();
    }

    /**
     * Convert an Eloquent category model into the same array shape used by the application.
     */
    private static function categoryFromModel(Category $category): array
    {
        $budgets = $category->budgets->map(function ($budget) {
            $spend = $budget->transactions->filter(fn ($transaction) => (float) $transaction->amount < 0)
                ->sum(fn ($transaction) => abs((float) $transaction->amount));
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

        $type = 'expense';
        if ($budgetTypes->contains('income')) {
            $type = 'income';
        } elseif ($budgetTypes->contains('saving')) {
            $type = 'saving';
        } elseif ($budgetTypes->isEmpty()) {
            $type = 'uncategorized';
        }

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
            'type' => $type,
            'budgets' => $budgets,
            'budget' => $totalBudget,
            'spend' => $totalSpend,
            'remaining' => $remaining,
            'unpaid' => collect($budgets)->sum('unpaid'),
            'overdue' => collect($budgets)->sum('overdue'),
        ];
    }
}
