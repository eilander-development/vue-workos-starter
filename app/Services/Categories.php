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

        if (Schema::hasTable('categories') && Schema::hasTable('budgets') && Schema::hasTable('transactions')) {
            self::$cachedCategories = Category::with(['budgets.transactions'])
                ->orderBy('name')
                ->get()
                ->map(function (Category $category) {
                    return self::categoryFromModel($category);
                })
                ->toArray();

            return self::$cachedCategories;
        }

        $categories = [
            1 => ['id' => 1, 'category' => 'Eten/drinken', 'slug' => 'eten-drinken', 'color' => 'red', 'icon' => 'Salad', 'budgets' => [
                ['id' => 1, 'name' => 'Restaurants', 'budget' => 100, 'spend' => 80],
                ['id' => 2, 'name' => 'Boodschappen', 'budget' => 450, 'spend' => 370],
                ['id' => 3, 'name' => 'Sport', 'budget' => 50, 'spend' => 50],
                ['id' => 4, 'name' => 'Overig', 'budget' => 50, 'spend' => 70.55],
            ]],
            2 => ['id' => 2, 'category' => 'Vervoer', 'slug' => 'vervoer', 'color' => 'yellow', 'icon' => 'Car', 'budgets' => [
                ['id' => 1, 'name' => 'Wegenbelasting', 'budget' => 124, 'spend' => 124],
            ]],
            3 => ['id' => 3, 'category' => 'Huisdieren', 'slug' => 'huisdieren', 'color' => 'indigo', 'icon' => 'Cat', 'budgets' => [
                ['id' => 1, 'name' => 'Timmie', 'budget' => 550, 'spend' => 0],
            ]],
            4 => ['id' => 4, 'category' => 'Woning', 'slug' => 'woning', 'color' => 'green', 'icon' => 'House', 'budgets' => [
                ['id' => 1, 'name' => 'Eten/drinken', 'budget' => 550, 'spend' => 0],
            ]],
            5 => ['id' => 5, 'category' => 'Verzekeringen', 'slug' => 'verzekeringen', 'color' => 'orange', 'icon' => 'ShieldAlert', 'budgets' => [
                ['id' => 1, 'name' => 'Eten/drinken', 'budget' => 550, 'spend' => 0],
            ]],
        ];

        self::$cachedCategories = collect($categories)->map(function ($category) {
            return self::category($category);
        })->toArray();

        return self::$cachedCategories;
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

        $totalBudget = collect($budgets)->sum('budget');
        $totalSpend = collect($budgets)->sum('spend');
        $remaining = $totalBudget - $totalSpend;

        return [
            'id' => $category->id,
            'category' => $category->name,
            'slug' => $category->slug,
            'icon' => $category->icon,
            'color' => $category->color,
            'budgets' => $budgets,
            'budget' => $totalBudget,
            'spend' => $totalSpend,
            'remaining' => $remaining,
            'unpaid' => collect($budgets)->sum('unpaid'),
            'overdue' => collect($budgets)->sum('overdue'),
        ];
    }

    /** 
     * Formats the category data by calculating remaining, overdue and unpaid amounts for each budget within the category.
     */
    private static function category(array $category): array
    {
        // build budgets with remaining, overdue and unpaid amounts
        $budgets = collect($category['budgets'])->map(function ($budget) {
            $budget['remaining'] = $budget['budget'] - $budget['spend'];

            if ($budget['budget'] - $budget['spend'] > 0) {
                $budget['overdue'] = 0;
                $budget['unpaid'] = $budget['budget'] - $budget['spend'];
            } else {
                $budget['overdue'] = $budget['spend'] - $budget['budget'];
                $budget['unpaid'] = 0;
            }
            return $budget;
        })->toArray();

        // we need to sum the budgets and spends of the category, so we can show the total budget and spend of the category
        $category['budget'] = collect($budgets)->sum('budget');
        $category['spend'] = collect($budgets)->sum('spend');
        // we need real numbers here, including negative ones, so we can't use the formatter here
        $category['remaining'] = $category['budget'] - $category['spend'];
        // we want to show the unpaid amount, which is the amount that is still unpaid, so we need to sum the unpaid amounts of the budgets
        $category['unpaid'] = collect($budgets)->sum('unpaid');
        $category['overdue'] = collect($budgets)->sum('overdue');
        $category['budgets'] = $budgets;

        return $category;
    }
}
