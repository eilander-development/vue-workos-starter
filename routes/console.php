<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('transactions:sample {--count=10} {--dry-run}', function () {
    $count = max(1, (int) $this->option('count'));
    $dryRun = (bool) $this->option('dry-run');

    $expenseCategory = Category::query()->where('type', 'expense')->with('budgets')->first();
    $incomeCategory = Category::query()->firstOrCreate(
        ['slug' => 'inkomsten'],
        ['name' => 'Inkomsten', 'type' => 'income', 'icon' => 'Euro', 'color' => 'green']
    );
    $savingCategory = Category::query()->firstOrCreate(
        ['slug' => 'sparen'],
        ['name' => 'Sparen', 'type' => 'saving', 'icon' => 'PiggyBank', 'color' => 'blue']
    );

    $expenseBudget = $expenseCategory?->budgets()->first();
    $incomeBudget = $incomeCategory->budgets()->firstOrCreate(['name' => 'Salaris'], ['budget' => 3200]);
    $savingBudget = $savingCategory->budgets()->firstOrCreate(['name' => 'Noodfonds'], ['budget' => 600]);

    if (!$expenseCategory || !$expenseBudget) {
        $this->error('Geen expense category/budget gevonden. Seed eerst categorieën.');
        return;
    }

    $descriptions = [
        'expense' => ['Supermarkt', 'Lunch', 'Tankstation', 'Koffiebar', 'Avondeten'],
        'income' => ['Salaris', 'Freelance', 'Teruggave', 'Bonus'],
        'saving' => ['Inleg spaarrekening', 'Extra sparen', 'Rente spaarpot'],
    ];

    for ($i = 0; $i < $count; $i++) {
        $type = ['expense', 'income', 'saving'][$i % 3];
        $daysAgo = random_int(0, 45);

        $amount = match ($type) {
            'expense' => -1 * random_int(8, 220),
            'income' => random_int(80, 3600),
            'saving' => random_int(25, 700),
        };

        $category = $type === 'expense' ? $expenseCategory : ($type === 'income' ? $incomeCategory : $savingCategory);
        $budget = $type === 'expense' ? $expenseBudget : ($type === 'income' ? $incomeBudget : $savingBudget);
        $description = $descriptions[$type][array_rand($descriptions[$type])] . ' #' . now()->format('ymd') . '-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);

        $data = [
            'amount' => (float) $amount,
            'category_id' => $category->id,
            'budget_id' => $budget->id,
            'type' => $type,
            'description' => $description,
            'date' => now()->subDays($daysAgo)->format('Y-m-d'),
            'icon' => $category->icon,
            'color' => $category->color,
        ];

        if ($dryRun) {
            $this->line(json_encode($data));
            continue;
        }

        Transaction::create($data);
    }

    $this->info($dryRun ? "Dry-run klaar ({$count} rijen)." : "Klaar: {$count} sample transacties toegevoegd.");
})->purpose('Voeg optioneel extra sample transacties toe voor expense/income/saving');
