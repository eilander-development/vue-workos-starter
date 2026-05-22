<?php

namespace App\Repositories;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Models\Budget;
use App\Models\Category;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function createCategory(array $data): Category
    {
        return Category::create($data);
    }

    public function updateCategory(int $categoryId, array $data): Category
    {
        $category = Category::findOrFail($categoryId);
        $category->update($data);

        return $category;
    }

    public function createBudget(int $categoryId, array $data): Budget
    {
        $category = Category::findOrFail($categoryId);

        return $category->budgets()->create($data);
    }

    public function updateBudget(int $categoryId, int $budgetId, array $data): Budget
    {
        $category = Category::findOrFail($categoryId);
        $budget = $category->budgets()->findOrFail($budgetId);
        $budget->update($data);

        return $budget;
    }

    public function deleteCategory(int $categoryId): void
    {
        $category = Category::findOrFail($categoryId);
        $category->budgets()->delete();
        $category->delete();
    }

    public function deleteBudget(int $categoryId, int $budgetId): void
    {
        $category = Category::findOrFail($categoryId);
        $budget = $category->budgets()->findOrFail($budgetId);
        $budget->delete();
    }
}
