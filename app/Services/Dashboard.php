<?php

namespace App\Services;

class Dashboard
{
    /**
     * Retrieves the budget expenses data for various categories.
     *
     * This method provides an array of budget expenses, including category IDs,
     * names, allocated amounts, and corresponding colors for representation.
     *
     * @return array A structured array containing the budget expenses details.
     */
    public static function stats() : array
    {
        $income = 3900;
        $expenses = 3258;
        return [
            'income' => $income,
            'expenses' => $expenses,
            'left' => $income - $expenses,
            'budgets' => count(self::monthlyExpenses()),
        ];
    }

    /**
     * Retrieves the budget expenses data for various categories.
     *
     * This method provides an array of budget expenses, including category IDs,
     * names, allocated amounts, and corresponding colors for representation.
     *
     * @return array A structured array containing the budget expenses details.
     */
    public static function monthlyExpenses() : array
    {
        return [
            ['id' => 1, 'category' => 'Marketing', 'amount' => 800, 'color' => 'bg-green-500'],
            ['id' => 2, 'category' => 'Sales', 'amount' => 300, 'color' => 'bg-yellow-500'],
            ['id' => 3, 'category' => 'Support', 'amount' => 150, 'color' => 'bg-red-500'],
            ['id' => 3, 'category' => 'Finance', 'amount' => 65, 'color' => 'bg-blue-500'],
        ];
    }

    /**
     * Generates the data series for the monthly expenses chart.
     *
     * This method returns an array containing the income and expenses data
     * series with their respective values and assigned colors.
     *
     * @return array An array with 'Income' and 'Expenses' datasets.
     */
    public static function yearlyExpensesChartSeries() : array
    {
        return [
            [
                'name' => 'Income',
                // 'data' => [3900, 3900, 3900, 3900, 4200, 3900, 0, 0, 0, 0, 0, 0],
                'color' => 'oklch(37.8% 0.077 168.94)',
                'data' => [
                    [
                        'x' => 'Jan',
                        'y' => 3900
                    ],
                    [
                        'x' => 'Feb',
                        'y' => 3900
                    ],
                    [
                        'x' => 'Mar',
                        'y' => 3900
                    ],
                    [
                        'x' => 'Apr',
                        'y' => 3900
                    ],
                    [
                        'x' => 'Mei',
                        'y' => 5900
                    ],
                    [
                        'x' => 'Jun',
                        'y' => 3900
                    ],
                    [
                        'x' => 'Jul',
                        'y' => 0
                    ],
                    [
                        'x' => 'Aug',
                        'y' => 0
                    ],
                    [
                        'x' => 'Sep',
                        'y' => 0
                    ],
                    [
                        'x' => 'Okt',
                        'y' => 0
                    ],
                    [
                        'x' => 'Nov',
                        'y' => 0
                    ],
                    [
                        'x' => 'Dec',
                        'y' => 0
                    ],
                ]
            ],
            [
                'name' => 'Expenses',
                // 'data' => [3500, 3600, 3789, 4100, 3456, 3850, 0, 0, 0, 0, 0, 0],
                'color' => 'oklch(39.6% 0.141 25.723)',
                'data' => [
                    [
                        'x' => 'Jan',
                        'y' => 3500
                    ],
                    [
                        'x' => 'Feb',
                        'y' => 3600
                    ],
                    [
                        'x' => 'Mar',
                        'y' => 3789
                    ],
                    [
                        'x' => 'Apr',
                        'y' => 4100
                    ],
                    [
                        'x' => 'Mei',
                        'y' => 3456
                    ],
                    [
                        'x' => 'Jun',
                        'y' => 3850
                    ],
                    [
                        'x' => 'Jul',
                        'y' => 0
                    ],
                    [
                        'x' => 'Aug',
                        'y' => 0
                    ],
                    [
                        'x' => 'Sep',
                        'y' => 0
                    ],
                    [
                        'x' => 'Okt',
                        'y' => 0
                    ],
                    [
                        'x' => 'Nov',
                        'y' => 0
                    ],
                    [
                        'x' => 'Dec',
                        'y' => 0
                    ],
                ]
            ],
        ];
    }
}
