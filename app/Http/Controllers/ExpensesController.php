<?php

namespace App\Http\Controllers;

use App\Services\Categories;
use App\Services\ReportingPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpensesController extends Controller
{
    public function __construct(
        protected Categories $categoriesService,
        protected ReportingPeriod $reportingPeriod,
    ) {}

    /**
     * Show the user's profile settings page.
     */
    public function index(Request $request, $category = null): Response
    {
        $month = (string) ($request->query('month') ?? $this->reportingPeriod->defaultPickerMonth());
        [$startDate, $endDate] = $this->reportingPeriod->periodForMonth($month, $this->reportingPeriod->configuredStartDay());
        $categories = array_values($this->categoriesService->list('expense', $startDate, $endDate));
        $selected = collect($categories)->firstWhere('slug', $category) ?? ($categories[0] ?? null);

        return Inertia::render('Expenses', [
            'month' => $month,
            'categories' => $categories,
            'selected' => $selected,
        ]);
    }
}
