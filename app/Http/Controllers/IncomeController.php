<?php

namespace App\Http\Controllers;

use App\Services\Categories;
use App\Services\ReportingPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IncomeController extends Controller
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
        $categories = array_values($this->categoriesService->list('income', $this->reportingPeriod->startOfCurrentMonthFromDay(20)));
        $selected = collect($categories)->firstWhere('slug', $category) ?? ($categories[0] ?? null);

        return Inertia::render('Income', [
            'categories' => $categories,
            'selected' => $selected,
        ]);
    }
}
