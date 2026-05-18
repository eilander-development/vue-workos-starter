<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Eten/drinken',
                'slug' => 'eten-drinken',
                'icon' => 'Salad',
                'color' => 'red',
                'budgets' => [
                    ['name' => 'Restaurants', 'budget' => 100, 'spend' => 80],
                    ['name' => 'Boodschappen', 'budget' => 450, 'spend' => 370],
                ],
            ],
            [
                'name' => 'Vervoer',
                'slug' => 'vervoer',
                'icon' => 'Car',
                'color' => 'yellow',
                'budgets' => [
                    ['name' => 'Wegenbelasting', 'budget' => 124, 'spend' => 124],
                ],
            ],
        ];

        foreach ($categories as $category) {
            $categoryModel = Category::updateOrCreate(
                ['slug' => $category['slug']],
                Arr::except($category, ['budgets'])
            );

            foreach ($category['budgets'] as $budget) {
                $categoryModel->budgets()->updateOrCreate(
                    ['name' => $budget['name']],
                    ['budget' => $budget['budget']]
                );
            }
        }
    }
}
