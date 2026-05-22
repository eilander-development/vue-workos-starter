<?php

namespace App\Http\Controllers;

use App\Services\Categories;
use App\Services\Dashboard;
use App\Services\ReportingPeriod;
use App\Support\PaginationData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected Categories $categoriesService,
        protected Dashboard $dashboardService,
        protected ReportingPeriod $reportingPeriod,
    ) {}

    /**
     * Show the user's profile settings page.
     */
    public function index(Request $request): Response
    {
        $transactions = $this->dashboardService->latestTransactions(
            page: $request->integer('transactions_page', 1),
            perPage: 100
        );

        return Inertia::render('Dashboard', [
            'categories' => $this->categoriesService->list('all', $this->reportingPeriod->startDate()),
            'stats' => $this->dashboardService->stats(),
            'latestTransactions' => $transactions->items(),
            'latestTransactionsPagination' => PaginationData::fromPaginator($transactions),
            'monthlyExpenses' => [
                'spend' => $this->dashboardService->monthlyExpensesSpend(),
                'budgets' => $this->dashboardService->monthlyExpensesBudgets(),
            ],
            'yearlyExpensesChart' => [
                'series' => $this->dashboardService->yearlyExpensesChartSeries(),
            ],
        ]);
    }
}
