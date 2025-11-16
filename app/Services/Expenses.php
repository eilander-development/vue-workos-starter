<?php

namespace App\Services;

class Expenses
{
    public static function list() : array
    {
        return [
            ['id' => 1, 'category' => 'Marketing', 'amount' => 800, 'color' => 'bg-green-500'],
            ['id' => 2, 'category' => 'Sales', 'amount' => 300, 'color' => 'bg-yellow-500'],
            ['id' => 3, 'category' => 'Support', 'amount' => 150, 'color' => 'bg-red-500'],
            ['id' => 3, 'category' => 'Finance', 'amount' => 65, 'color' => 'bg-blue-500'],
        ];
    }
}