<?php

namespace App\Http\Controllers;

use App\Services\Categories;
use App\Services\Dashboard;
use App\Services\EnableBanking;
use App\Services\EnableBankingDataService;
use App\Services\ReportingPeriod;
use App\Support\PaginationData;
use Illuminate\Http\Request;
use App\Models\DynamicBudget;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        protected Categories $categoriesService,
        protected Dashboard $dashboardService,
        protected ReportingPeriod $reportingPeriod,
        protected EnableBanking $enableBanking,
        protected EnableBankingDataService $enableBankingDataService,
    ) {}

    /**
     * Show the user's profile settings page.
     */
    public function index(Request $request): Response
    {
        $month = (string) ($request->query('month') ?? $this->reportingPeriod->defaultPickerMonth());
        [$startDate, $endDate] = $this->reportingPeriod->periodForMonth($month, $this->reportingPeriod->configuredStartDay());
        return Inertia::render('Dashboard', [
            'month' => $month,
            'categories' => [],
            'stats' => null,
            'latestTransactions' => [],
            'latestTransactionsPagination' => null,
            'monthlyExpenses' => [
                'spend' => [],
                'budgets' => [],
            ],
            'yearlyExpensesChart' => [
                'series' => [],
            ],
        ]);
    }

    public function dashboardData(Request $request): JsonResponse
    {
        $month = (string) ($request->query('month') ?? $this->reportingPeriod->defaultPickerMonth());
        [$startDate, $endDate] = $this->reportingPeriod->periodForMonth($month, $this->reportingPeriod->configuredStartDay());
        $transactions = $this->dashboardService->latestTransactions(
            page: $request->integer('transactions_page', 1),
            perPage: 25,
            start: $startDate,
            end: $endDate,
        );
        $enableBankingBalance = $this->resolveEnableBankingBalance($request);

        return response()->json([
            'categories' => $this->categoriesService->list('all', $startDate, $endDate),
            'stats' => $this->dashboardService->stats($startDate, $endDate, $enableBankingBalance, $month),
            'latestTransactions' => $transactions->items(),
            'latestTransactionsPagination' => PaginationData::fromPaginator($transactions),
            'monthlyExpenses' => [
                'spend' => $this->dashboardService->monthlyExpensesSpend($startDate, $endDate),
                'budgets' => $this->dashboardService->monthlyExpensesBudgets($startDate, $endDate),
            ],
        ]);
    }

    public function yearlyExpensesChart(Request $request): JsonResponse
    {
        $year = (int) ($request->query('year') ?: now()->year);

        return response()->json([
            'series' => $this->dashboardService->yearlyExpensesChartSeriesForYear($year),
        ]);
    }

    public function storeDynamicBudgets(Request $request)
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'rows' => ['present', 'array'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.budget' => ['required', 'numeric'],
            'rows.*.paid' => ['nullable', 'numeric'],
        ]);

        $rows = collect($data['rows'])
            ->map(fn (array $row, int $index) => [
                'month' => $data['month'],
                'name' => trim((string) $row['name']),
                'budget' => (float) $row['budget'],
                'paid' => (float) ($row['paid'] ?? 0),
                'sort_order' => $index,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        DB::transaction(function () use ($data, $rows) {
            DynamicBudget::query()->where('month', $data['month'])->delete();

            if ($rows !== []) {
                DynamicBudget::query()->insert($rows);
            }
        });

        return back()->with('success', 'Dynamische budgetten opgeslagen.');
    }

    private function resolveEnableBankingBalance(Request $request): ?float
    {
        try {
            $list = $request->session()->get('eb_cached_accounts');
            if (! $list && $request->session()->has('eb_session_id')) {
                $sessionData = $this->enableBanking->getSessionData((string) $request->session()->get('eb_session_id'));
                $list = $sessionData['accounts_data'] ?? ($sessionData['accounts'] ?? []);
            }

            if (! is_array($list) || $list === []) {
                return null;
            }

            $mapped = $this->enableBankingDataService->mapAccountBalances($list, $this->enableBanking);

            return (float) collect($mapped)->sum(fn (array $account) => (float) ($account['balance'] ?? 0));
        } catch (\Throwable) {
            return null;
        }
    }
}
