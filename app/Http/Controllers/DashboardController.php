<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\WorkOS\Http\Requests\AuthKitAccountDeletionRequest;

class DashboardController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'monthlyBudgetExpenses' => \App\Services\Expenses::monthlyBudgetExpenses(),
            'monthlyExpensesChart' => [
                'series' => \App\Services\Expenses::yearlyExpensesChartSeries(),
                'months' => \App\Services\Expenses::yearlyExpensesChartMonths()
            ],
        ]);
    }
}
