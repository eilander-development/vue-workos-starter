<?php

namespace App\Services;

class Dashboard
{
    /**
     * Retrieves the latest transactions.
     *
     * This method returns an array containing the most recent transactions,
     * including details such as the transaction ID, amount, category, and date.
     *
     * @return array An array of the latest transactions with key details.
     */
    public static function latestTransactions() : array
    {
        return [
            [
                'id' => 1,
                'amount' => -23.30,
                'categoryId' => 1,
                'description' => 'Pizza Palace',
                'date' => now()->format('d-m-Y'),
            ],
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
        $budgets = collect([
            ['categoryId' => 1, 'amount' => 500],
            ['categoryId' => 2, 'amount' => 300],
            ['categoryId' => 3, 'amount' => 50],
            ['categoryId' => 4, 'amount' => 489],
            ['categoryId' => 5, 'amount' => 165],
        ]);

        return $budgets->sortByDesc('amount')->values()->all();
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
                'color' => 'oklch(50.8% 0.118 165.612)',
                //'color' => 'oklch(37.8% 0.077 168.94)',
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
                'color' => 'oklch(44.4% 0.177 26.899)',
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
