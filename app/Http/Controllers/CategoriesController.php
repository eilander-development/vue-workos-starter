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

        return redirect()->route('categories.index');
    }

    public function update(Request $request, int $categoryId): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:categories,slug,' . $categoryId],
            'type' => ['required', 'string', 'in:expense,income,saving,uncategorized'],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:50'],
        ]);

        $repository = new CategoryRepository();
        $repository->updateCategory($categoryId, $data);

        return redirect()->route('categories.index');
    }

    public function storeBudget(Request $request, int $categoryId): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'budget' => ['required', 'numeric', 'min:0'],
        ]);

        $repository = new CategoryRepository();
        $repository->createBudget($categoryId, $data);

        return redirect()->route('categories.index');
    }
}
