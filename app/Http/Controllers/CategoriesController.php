<?php

namespace App\Http\Controllers;

use App\Repositories\CategoryRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoriesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Categories', [
            'categories' => \App\Services\Categories::list(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:categories,slug'],
            'type' => ['required', 'string', 'in:expense,income,saving,uncategorized'],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:50'],
        ]);

        $repository = new CategoryRepository();
        $repository->createCategory($data);

        return redirect()->route('categories');
    }

    public function update(Request $request, int $categoryId): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:categories,slug,' . $categoryId],
            'type' => ['required', 'string', 'in:expense,income,saving,uncategorized'],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:50'],
            'budgets' => ['nullable', 'array'],
            'budgets.*.id' => ['nullable', 'integer'],
            'budgets.*.name' => ['nullable', 'string', 'max:255'],
            'budgets.*.budget' => ['nullable', 'numeric', 'min:0'],
        ]);

        $repository = new CategoryRepository();
        $repository->updateCategory($categoryId, [
            'name' => $data['name'],
            'slug' => $data['slug'],
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
}
