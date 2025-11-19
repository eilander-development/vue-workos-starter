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
            'categories' => \App\Services\Categories::list(),
            'stats' => \App\Services\Dashboard::stats(),
            'latestTransactions' => \App\Services\Dashboard::latestTransactions(),
            'monthlyExpenses' => \App\Services\Dashboard::monthlyExpenses(),
            'yearlyExpensesChart' => [
                'series' => \App\Services\Dashboard::yearlyExpensesChartSeries()
            ],
        ]);
    }
}
