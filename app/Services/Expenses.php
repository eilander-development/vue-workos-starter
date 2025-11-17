<?php

namespace App\Services;

class Expenses
{
    /**
     * Retrieves the budget expenses data for various categories.
     *
     * This method provides an array of budget expenses, including category IDs,
     * names, allocated amounts, and corresponding colors for representation.
     *
     * @return array A structured array containing the budget expenses details.
     */
    public static function monthlyBudgetExpenses() : array
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
                'data' => [3900, 3900, 3900, 3900, 4200, 3900, 0, 0, 0, 0, 0, 0],
                'color' => 'oklch(79.2% 0.209 151.711)',
            ],
            [
                'name' => 'Expenses',
                'data' => [-3500, -3600, -3789, -4100, -3456, -3850, 0, 0, 0, 0, 0, 0],
                'color' => 'oklch(70.4% 0.191 22.216)',
            ],
        ];
    }

    /**
     * Generates an array of days for the current month in 'd M' format.
     *
     * @return array An array of strings representing the days of the current month.
     */
    public static function yearlyExpensesChartMonths() : array
    {
        $months = [];
        $startMonth = \Carbon\Carbon::now()->startOfYear()->format('M');
        $endMonth = \Carbon\Carbon::now()->endOfYear()->format('M');
        $monthRange = \Carbon\CarbonPeriod::create($startMonth, '1 month', $endMonth);
        foreach ($monthRange as $month){
            $months[] = \Carbon\Carbon::parse($month)->format('M');
        }
        return $months;
    }
}
