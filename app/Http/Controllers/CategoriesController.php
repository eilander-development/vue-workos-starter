<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Http\Requests\Categories\StoreBudgetRequest;
use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\Categories;
use App\Services\ReportingPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoriesController extends Controller
{
    public function __construct(
        protected CategoryRepositoryInterface $categoryRepository,
        protected Categories $categoriesService,
        protected ReportingPeriod $reportingPeriod,
    ) {}

    public function index(Request $request): Response
    {
        $filter = $request->string('filter')->toString();
        $allowedFilters = ['all', 'expense', 'income', 'saving', 'uncategorized'];
        if (! in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }

        $pageNumber = max(1, (int) $request->input('page', 1));
        $startDate = $this->reportingPeriod->startOfCurrentMonthFromDay(20);
        $categories = $this->categoriesService->list($filter, $startDate);
        $allCategories = $this->categoriesService->list('all', $startDate);

        $paginatedCategories = array_values(array_slice($categories, ($pageNumber - 1) * 100, 100));
        $totalCategories = count($categories);

        return Inertia::render('Categories', [
            'categories' => $paginatedCategories,
            'stats' => $this->categoriesService->stats($allCategories),
            'activeFilter' => $filter,
            'pagination' => [
                'current_page' => $pageNumber,
                'last_page' => (int) ceil($totalCategories / 100),
                'per_page' => 100,
                'total' => $totalCategories,
                'from' => $totalCategories ? (($pageNumber - 1) * 100) + 1 : 0,
                'to' => min($pageNumber * 100, $totalCategories),
            ],
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $slug = $this->generateUniqueSlug($data['name']);

        $category = $this->categoryRepository->createCategory([
            'name' => $data['name'],
            'slug' => $slug,
            'type' => $data['type'],
            'icon' => $data['icon'] ?? null,
            'color' => $data['color'] ?? null,
        ]);

        foreach ($data['budgets'] ?? [] as $budgetData) {
            $name = trim((string) ($budgetData['name'] ?? ''));
            $amount = $budgetData['budget'] ?? null;

            if ($name === '' || $amount === null || $amount === '') {
                continue;
            }

            $this->categoryRepository->createBudget($category->id, [
                'name' => $name,
                'budget' => $amount,
            ]);
        }

        return redirect()->route('categories');
    }

    public function update(UpdateCategoryRequest $request, int $categoryId): RedirectResponse
    {
        $data = $request->validated();

        $slug = $this->generateUniqueSlug($data['name'], $categoryId);

        $this->categoryRepository->updateCategory($categoryId, [
            'name' => $data['name'],
            'slug' => $slug,
            'type' => $data['type'],
            'icon' => $data['icon'] ?? null,
            'color' => $data['color'] ?? null,
        ]);

        foreach ($data['budgets'] ?? [] as $budgetData) {
            $name = trim((string) ($budgetData['name'] ?? ''));
            $amount = $budgetData['budget'] ?? null;

            if ($name === '' || $amount === null || $amount === '') {
                continue;
            }

            $payload = [
                'name' => $name,
                'budget' => $amount,
            ];

            if (! empty($budgetData['id'])) {
                $this->categoryRepository->updateBudget($categoryId, (int) $budgetData['id'], $payload);
            } else {
                $this->categoryRepository->createBudget($categoryId, $payload);
            }
        }

        return redirect()->route('categories');
    }

    public function storeBudget(StoreBudgetRequest $request, int $categoryId): RedirectResponse
    {
        $data = $request->validated();

        $this->categoryRepository->createBudget($categoryId, $data);

        return redirect()->route('categories');
    }

    public function destroy(int $categoryId): RedirectResponse
    {
        $this->categoryRepository->deleteCategory($categoryId);

        return redirect()->route('categories');
    }

    public function destroyBudget(int $categoryId, int $budgetId): RedirectResponse
    {
        $this->categoryRepository->deleteBudget($categoryId, $budgetId);

        return redirect()->route('categories');
    }

    private function generateUniqueSlug(string $name, ?int $ignoreCategoryId = null): string
    {
        $baseSlug = Str::slug($name);
        $stem = $baseSlug !== '' ? $baseSlug : 'categorie';
        $slug = $stem;
        $counter = 1;

        while (
            Category::query()
                ->when($ignoreCategoryId, fn ($query) => $query->where('id', '!=', $ignoreCategoryId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $counter++;
            $slug = $stem.'-'.$counter;
        }

        return $slug;
    }
}
