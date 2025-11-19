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
        return [
            1 => ['id' => 1, 'category' => 'Eten/drinken', 'color' => 'red', 'icon' => 'Salad', 'budget' => 550],
            2 => ['id' => 2, 'category' => 'Vervoer', 'color' => 'yellow', 'icon' => 'Car', 'budget' => 480],
            3 => ['id' => 3, 'category' => 'Huisdieren', 'color' => 'indigo', 'icon' => 'Cat', 'budget' => 50],
            4 => ['id' => 4, 'category' => 'Woning', 'color' => 'blue', 'icon' => 'House', 'budget' => 1680],
            5 => ['id' => 5, 'category' => 'Verzekeringen', 'color' => 'orange', 'icon' => 'ShieldAlert', 'budget' => 190],
        ];
    }
}
