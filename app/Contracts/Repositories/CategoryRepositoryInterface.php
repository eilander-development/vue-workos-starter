<?php

namespace App\Contracts\Repositories;

use App\Models\Budget;
use App\Models\Category;

interface CategoryRepositoryInterface
{
    public function createCategory(array $data): Category;

    public function updateCategory(int $categoryId, array $data): Category;

    public function createBudget(int $categoryId, array $data): Budget;

    public function updateBudget(int $categoryId, int $budgetId, array $data): Budget;

    public function deleteCategory(int $categoryId): void;

    public function deleteBudget(int $categoryId, int $budgetId): void;
}
