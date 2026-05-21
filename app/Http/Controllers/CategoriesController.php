<?php

namespace App\Http\Controllers;

use App\Repositories\CategoryRepository;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoriesController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->string('filter')->toString();
        $allowedFilters = ['all', 'expense', 'income', 'saving', 'uncategorized'];
        if (!in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }

        $categories = \App\Services\Categories::list($filter);
        $allCategories = \App\Services\Categories::list('all');

        return Inertia::render('Categories', [
            'categories' => $categories,
            'stats' => \App\Services\Categories::stats($allCategories),
            'activeFilter' => $filter,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:expense,income,saving,uncategorized'],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:50'],
            'budgets' => ['nullable', 'array'],
            'budgets.*.name' => ['nullable', 'string', 'max:255'],
            'budgets.*.budget' => ['nullable', 'numeric', 'min:0'],
        ]);

        $slug = $this->generateUniqueSlug($data['name']);

        $repository = new CategoryRepository();
        $category = $repository->createCategory([
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

            $repository->createBudget($category->id, [
                'name' => $name,
                'budget' => $amount,
            ]);
        }

        return redirect()->route('categories');
    }

    public function update(Request $request, int $categoryId): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:expense,income,saving,uncategorized'],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:50'],
            'budgets' => ['nullable', 'array'],
            'budgets.*.id' => ['nullable', 'integer'],
            'budgets.*.name' => ['nullable', 'string', 'max:255'],
            'budgets.*.budget' => ['nullable', 'numeric', 'min:0'],
        ]);

        $slug = $this->generateUniqueSlug($data['name'], $categoryId);

        $repository = new CategoryRepository();
        $repository->updateCategory($categoryId, [
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

            if (!empty($budgetData['id'])) {
                $repository->updateBudget($categoryId, (int) $budgetData['id'], $payload);
            } else {
                $repository->createBudget($categoryId, $payload);
            }
        }

        return redirect()->route('categories');
    }

    public function storeBudget(Request $request, int $categoryId): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'budget' => ['required', 'numeric', 'min:0'],
        ]);

        $repository = new CategoryRepository();
        $repository->createBudget($categoryId, $data);

        return redirect()->route('categories');
    }

    public function destroy(int $categoryId): RedirectResponse
    {
        $repository = new CategoryRepository();
        $repository->deleteCategory($categoryId);

        return redirect()->route('categories');
    }

    public function destroyBudget(int $categoryId, int $budgetId): RedirectResponse
    {
        $repository = new CategoryRepository();
        $repository->deleteBudget($categoryId, $budgetId);

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
            $slug = $stem . '-' . $counter;
        }

        return $slug;
    }
}
