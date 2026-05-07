<?php

namespace App\Services;

class Categories
{
    /**
     * Retrieves the budget expenses data for various categories.
     *
     * This method provides an array of budget expenses, including category IDs,
     * names, allocated amounts, and corresponding colors for representation.
     *
     * @return array A structured array containing the budget expenses details.
     */
    public static function list() : array
    {
        $categories = [
            1 => ['id' => 1, 'category' => 'Eten/drinken', 'slug' => 'eten-drinken', 'color' => 'red', 'icon' => 'Salad', 'budget' => 550, 'spend' => 450, 'budgets' => [
                ['id' => 1, 'name' => 'Restaurants', 'budget' => 100, 'spend' => 80],
                ['id' => 2, 'name' => 'Boodschappen', 'budget' => 450, 'spend' => 370],
                ['id' => 3, 'name' => 'Sport', 'budget' => 50, 'spend' => 50],
                ['id' => 4, 'name' => 'Overig', 'budget' => 50, 'spend' => 70.55],
            ]],
            2 => ['id' => 2, 'category' => 'Vervoer', 'slug' => 'vervoer', 'color' => 'yellow', 'icon' => 'Car', 'budget' => 480, 'spend' => 380, 'budgets' => [
                ['id' => 1, 'name' => 'Wegenbelasting', 'budget' => 124, 'spend' => 124],
            ]],
            3 => ['id' => 3, 'category' => 'Huisdieren', 'slug' => 'huisdieren', 'color' => 'indigo', 'icon' => 'Cat', 'budget' => 50, 'spend' => 0, 'budgets' => [
                ['id' => 1, 'name' => 'Timmie', 'budget' => 550, 'spend' => 0],
            ]],
            4 => ['id' => 4, 'category' => 'Woning', 'slug' => 'woning', 'color' => 'green', 'icon' => 'House', 'budget' => 1680, 'spend' => 0, 'budgets' => [
                ['id' => 1, 'name' => 'Eten/drinken', 'budget' => 550, 'spend' => 0],
            ]],
            5 => ['id' => 5, 'category' => 'Verzekeringen', 'slug' => 'verzekeringen', 'color' => 'orange', 'icon' => 'ShieldAlert', 'budget' => 190, 'spend' => 0, 'budgets' => [
                ['id' => 1, 'name' => 'Eten/drinken', 'budget' => 550, 'spend' => 0],
            ]],
        ];

        return collect($categories)->map(function ($category) {
            return self::category($category);
        })->toArray();
    }

    /** 
     * Formats the category data by calculating remaining, overdue, and unpaid amounts for each budget within the category.
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
