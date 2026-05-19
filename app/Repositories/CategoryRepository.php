<?php

namespace App\Repositories;

use App\Models\Budget;
use App\Models\Category;

class CategoryRepository
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
}
