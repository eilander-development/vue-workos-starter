<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\BudgetMonthValue;
use App\Models\Category;
use App\Models\ImportRule;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SparenStateService
{
    public const MONTHS = [
        'jan' => 'Januari',
        'feb' => 'Februari',
        'mrt' => 'Maart',
        'apr' => 'April',
        'mei' => 'Mei',
        'jun' => 'Juni',
        'jul' => 'Juli',
        'aug' => 'Augustus',
        'sep' => 'September',
        'okt' => 'Oktober',
        'nov' => 'November',
        'dec' => 'December',
    ];

    public function build(int $year = 2026): array
    {
        $categories = Category::query()->orderBy('id')->get();
        $budgets = Budget::query()->with('category')->orderBy('sort_order')->orderBy('id')->get();
        $monthValues = BudgetMonthValue::query()->where('year', $year)->get()->groupBy('budget_id');
        $transactions = Transaction::query()->with(['category', 'budget', 'importRule'])->orderByDesc('date')->orderByDesc('id')->get();
        $rules = ImportRule::query()->with(['category', 'budget'])->orderBy('id')->get();
        $goals = SavingsGoal::query()->orderBy('id')->get();
        $accounts = BankAccount::query()->orderBy('id')->get();

        $txPayload = $transactions->map(fn (Transaction $tx) => $this->mapTransaction($tx))->values();

        $monthlyBudgets = [];
        foreach (self::MONTHS as $monthId => $monthName) {
            $prefix = sprintf('%d-%02d', $year, $this->monthNumber($monthId));
            $items = $budgets->map(function (Budget $budget) use ($monthValues, $monthId, $txPayload, $prefix) {
                $monthValue = optional($monthValues->get($budget->id))->firstWhere('month_id', $monthId);
                $entries = collect($monthValue?->entries ?? [])
                    ->filter(fn ($row) => is_array($row))
                    ->map(fn (array $row) => [
                        'id' => (string) ($row['id'] ?? uniqid('entry-', true)),
                        'description' => trim((string) ($row['description'] ?? '')),
                        'amount' => round((float) ($row['amount'] ?? 0), 2),
                    ])
                    ->filter(fn (array $row) => $row['description'] !== '' || $row['amount'] != 0.0)
                    ->values()
                    ->all();

                $estimatedFromEntries = round(collect($entries)->sum('amount'), 2);
                $estimated = $entries !== []
                    ? $estimatedFromEntries
                    : (float) ($monthValue?->estimated ?? $budget->budget);

                $matching = $txPayload->filter(function (array $tx) use ($budget, $prefix) {
                    return str_starts_with((string) $tx['date'], $prefix)
                        && ($tx['budgetItemId'] ?? null) === $budget->key;
                });
                $paid = round((float) $matching->sum(fn (array $tx) => abs((float) $tx['amount'])), 2);

                return [
                    'id' => $budget->key,
                    'name' => $budget->name,
                    'group' => $budget->category?->name ?? 'Overige Kosten',
                    'type' => $this->toSparenType($budget->category?->type),
                    'estimated' => $estimated,
                    'actual' => $estimated,
                    'paidOrReceived' => $paid,
                    'paymentCount' => $matching->count(),
                    'notes' => $budget->notes,
                    'isPaid' => $estimated > 0 && $paid >= $estimated,
                    'monthEntries' => $entries,
                ];
            })->values()->all();

            $checking = $accounts->firstWhere('type', 'checking');
            $isCurrent = ((int) now('Europe/Amsterdam')->format('n')) === $this->monthNumber($monthId);

            $monthlyBudgets[] = [
                'monthId' => $monthId,
                'monthName' => $monthName,
                'year' => $year,
                'opRekening' => $isCurrent && $checking ? (float) $checking->balance : 0,
                'items' => $items,
            ];
        }

        return [
            'categories' => $categories->map(fn (Category $category) => [
                'id' => $category->key,
                'name' => $category->name,
                'type' => $this->toSparenType($category->type),
                'icon' => $category->icon,
                'color' => $category->color,
                'description' => $category->description,
                'isDefault' => (bool) $category->is_default,
            ])->values()->all(),
            'monthlyBudgets' => $monthlyBudgets,
            'transactions' => $txPayload->all(),
            'rules' => $rules->map(fn (ImportRule $rule) => [
                'id' => $rule->key,
                'name' => $rule->name,
                'keyword' => $rule->match_value,
                'matchField' => $rule->match_field ?: 'description',
                'targetGroup' => $rule->category?->name,
                'targetBudgetItemId' => $rule->budget?->key,
                'targetType' => $this->toSparenType($rule->category?->type),
                'isActive' => (bool) $rule->is_active,
                'matchedCount' => Transaction::query()->where('rule_id', $rule->id)->count(),
            ])->values()->all(),
            'bankAccounts' => $accounts->map(fn (BankAccount $account) => $this->mapAccount($account))->values()->all(),
            'savingsGoals' => $goals->map(fn (SavingsGoal $goal) => [
                'id' => $goal->key,
                'name' => $goal->name,
                'accountIban' => $goal->account_iban,
                'bankName' => $goal->bank_name,
                'targetAmount' => (float) $goal->target_amount,
                'initialAmount' => (float) $goal->initial_amount,
                'monthlyContribution' => (float) $goal->monthly_contribution,
                'color' => $goal->color,
                'iconName' => $goal->icon_name,
                'notes' => $goal->notes,
                'categoryBudgetItemId' => $goal->budget_key,
                'kind' => $goal->kind === 'pot' ? 'pot' : 'goal',
            ])->values()->all(),
            'savingsHistory' => $this->savingsHistory($year, $txPayload, $goals),
            'enableBankingConnected' => session()->has('eb_session_id') && filled(session('eb_session_id')),
        ];
    }

    public function persist(array $payload): void
    {
        // Bulk-snapshots schrijven geen data meer. Oude tabbladen mogen
        // geen collecties meer overschrijven of verwijderen.
    }

    public function persistTransactions(array $rows): void
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $this->writeTransaction($row);
            }
        });
    }

    public function deleteTransaction(string $key): bool
    {
        $tx = Transaction::query()->where('key', $key)->first();
        if (! $tx) {
            return false;
        }

        $tx->delete();

        return true;
    }

    public function persistRule(array $row): void
    {
        $budget = Budget::query()->where('key', $row['targetBudgetItemId'] ?? '')->first()
            ?? Budget::query()->whereHas('category', fn ($query) => $query->where('name', $row['targetGroup'] ?? ''))->first()
            ?? Budget::query()->first();
        $category = Category::query()->where('name', $row['targetGroup'] ?? '')->first() ?? $budget?->category;

        ImportRule::query()->updateOrCreate(
            ['key' => $row['id']],
            [
                'name' => $row['name'],
                'type' => ($row['matchField'] ?? 'description') === 'counterparty' ? 'iban' : 'description',
                'match_value' => $row['keyword'],
                'match_field' => $row['matchField'] ?? 'description',
                'is_active' => (bool) ($row['isActive'] ?? true),
                'category_id' => $category?->id ?? Category::query()->value('id'),
                'budget_id' => $budget?->id,
            ]
        );
    }

    public function deleteRule(string $key): bool
    {
        $rule = ImportRule::query()->where('key', $key)->first();
        if (! $rule) {
            return false;
        }

        Transaction::query()->where('rule_id', $rule->id)->update(['rule_id' => null]);
        $rule->delete();

        return true;
    }

    public function persistCategory(array $row): void
    {
        Category::query()->updateOrCreate(
            ['key' => $row['id']],
            [
                'name' => $row['name'],
                'slug' => Str::slug($row['name']).'-'.$row['id'],
                'type' => $this->fromSparenType($row['type'] ?? 'uitgaven'),
                'icon' => $row['icon'] ?? null,
                'color' => $row['color'] ?? null,
                'description' => $row['description'] ?? null,
                'is_default' => (bool) ($row['isDefault'] ?? false),
            ]
        );
    }

    public function deleteCategory(string $key): bool
    {
        $category = Category::query()->where('key', $key)->first();
        if (! $category) {
            return false;
        }

        $fallback = Category::query()->where('name', 'Overige Kosten')->where('id', '!=', $category->id)->first()
            ?? Category::query()->where('id', '!=', $category->id)->first();

        if ($fallback) {
            Budget::query()->where('category_id', $category->id)->update(['category_id' => $fallback->id]);
            ImportRule::query()->where('category_id', $category->id)->update(['category_id' => $fallback->id]);
            Transaction::query()->where('category_id', $category->id)->update(['category_id' => $fallback->id]);
        }

        $category->delete();

        return true;
    }

    public function persistSavingsGoal(array $row): void
    {
        SavingsGoal::query()->updateOrCreate(
            ['key' => $row['id']],
            [
                'name' => $row['name'],
                'account_iban' => strtoupper(preg_replace('/\s+/', '', $row['accountIban'] ?? '') ?? ''),
                'bank_name' => $row['bankName'] ?? null,
                'target_amount' => $row['targetAmount'] ?? 0,
                'initial_amount' => $row['initialAmount'] ?? 0,
                'monthly_contribution' => $row['monthlyContribution'] ?? 0,
                'color' => $row['color'] ?? null,
                'icon_name' => $row['iconName'] ?? null,
                'notes' => $row['notes'] ?? null,
                'budget_key' => $row['categoryBudgetItemId'] ?? null,
                'kind' => ($row['kind'] ?? 'goal') === 'pot' ? 'pot' : 'goal',
            ]
        );
    }

    public function deleteSavingsGoal(string $key): bool
    {
        $goal = SavingsGoal::query()->where('key', $key)->first();
        if (! $goal) {
            return false;
        }

        $goal->delete();

        return true;
    }

    public function persistBudgetItem(array $item): void
    {
        $category = Category::query()->where('name', $item['group'] ?? '')->first();
        $budget = Budget::query()->updateOrCreate(
            ['key' => $item['id']],
            [
                'category_id' => $category?->id ?? Category::query()->value('id'),
                'name' => $item['name'],
                'budget' => $item['estimated'] ?? $item['actual'] ?? 0,
                'notes' => $item['notes'] ?? null,
            ]
        );

        $year = (int) ($item['year'] ?? 2026);
        $amounts = $item['monthlyAmounts'] ?? [];
        $entriesByMonth = $item['monthlyEntries'] ?? [];

        if ($amounts === [] && isset($item['monthId'])) {
            $amounts = [$item['monthId'] => $item['estimated'] ?? $item['actual'] ?? 0];
        }

        $monthIds = collect(array_keys($amounts))
            ->merge(array_keys($entriesByMonth))
            ->unique()
            ->values();

        foreach ($monthIds as $monthId) {
            $entries = collect($entriesByMonth[$monthId] ?? [])
                ->filter(fn ($row) => is_array($row))
                ->map(function (array $row) {
                    return [
                        'id' => (string) ($row['id'] ?? uniqid('entry-', true)),
                        'description' => trim((string) ($row['description'] ?? '')),
                        'amount' => round((float) ($row['amount'] ?? 0), 2),
                    ];
                })
                ->filter(fn (array $row) => $row['description'] !== '' || abs($row['amount']) > 0.00001)
                ->values()
                ->all();

            $estimated = $entries !== []
                ? round(collect($entries)->sum('amount'), 2)
                : (float) ($amounts[$monthId] ?? 0);

            BudgetMonthValue::query()->updateOrCreate(
                [
                    'budget_id' => $budget->id,
                    'month_id' => (string) $monthId,
                    'year' => $year,
                ],
                [
                    'estimated' => $estimated,
                    'entries' => $entries !== [] ? $entries : null,
                ]
            );
        }
    }

    public function deleteBudgetItem(string $key): bool
    {
        $budget = Budget::query()->where('key', $key)->first();
        if (! $budget) {
            return false;
        }

        Transaction::query()->where('budget_id', $budget->id)->update(['budget_id' => null]);
        $budget->monthValues()->delete();
        $budget->delete();

        return true;
    }

    private function writeTransaction(array $row): void
    {
        $existing = Transaction::query()->where('key', $row['id'] ?? '')->first();
        $sourceType = $this->fromSparenSource($row['source'] ?? ($existing?->source_type === 'api' ? 'EnableBanking' : ($existing?->source_type === 'csv' ? 'CSV-import' : 'Handmatig')));

        // Bankmutaties komen alleen via de importer binnen.
        if (! $existing && $sourceType !== 'manual') {
            return;
        }

        $budget = Budget::query()->where('key', $row['budgetItemId'] ?? '')->first();
        $category = Category::query()->where('name', $row['categoryGroup'] ?? '')->first() ?? $budget?->category;
        $rule = ImportRule::query()->where('key', $row['matchedRuleId'] ?? '')->first();

        Transaction::query()->updateOrCreate(
            ['key' => $row['id']],
            [
                'date' => $row['date'] ?? $existing?->date,
                'description' => $row['description'] ?? $existing?->description,
                'amount' => $row['amount'] ?? $existing?->amount,
                'type' => $this->fromSparenTxType($row['type'] ?? null) ?: ($existing?->type ?? 'expense'),
                'category_id' => $category?->id ?? $existing?->category_id,
                'budget_id' => array_key_exists('budgetItemId', $row)
                    ? ($budget?->id ?? (empty($row['budgetItemId']) ? null : $existing?->budget_id))
                    : $existing?->budget_id,
                'rule_id' => array_key_exists('matchedRuleId', $row)
                    ? ($rule?->id ?? (empty($row['matchedRuleId']) ? null : $existing?->rule_id))
                    : ($rule?->id ?? $existing?->rule_id),
                'account_iban' => $row['accountIban'] ?? $existing?->account_iban,
                'counterparty_iban' => $this->extractIban($row['counterparty'] ?? '') ?: $existing?->counterparty_iban,
                'counterparty_name' => array_key_exists('counterparty', $row)
                    ? ($row['counterparty'] ?? null)
                    : $existing?->counterparty_name,
                'is_pending' => (bool) ($row['isPending'] ?? $existing?->is_pending ?? false),
                'booked_time' => ! empty($row['time']) ? $row['time'] : $existing?->booked_time,
                'source_type' => $existing?->source_type ?? $sourceType,
            ]
        );
    }

    private function mapTransaction(Transaction $tx): array
    {
        $type = match ($tx->type) {
            'income' => 'Inkomsten',
            'saving' => 'Sparen',
            default => 'Uitgave',
        };

        if ($tx->category?->type === 'saving') {
            $type = 'Sparen';
        }

        return [
            'id' => $tx->key ?: 'tx-'.$tx->id,
            'date' => optional($tx->date)->format('Y-m-d'),
            'time' => $tx->booked_time ? substr((string) $tx->booked_time, 0, 5) : null,
            'description' => $tx->description,
            'amount' => (float) $tx->amount,
            'type' => $type,
            'categoryGroup' => $tx->category?->name ?? 'Ongecategoriseerd',
            'budgetItemId' => $tx->budget?->key,
            'accountIban' => $tx->account_iban ?? '',
            'counterparty' => $tx->counterparty_name ?: $tx->counterparty_iban,
            'isPending' => (bool) $tx->is_pending,
            'matchedRuleId' => $tx->importRule?->key,
            'source' => match ($tx->source_type) {
                'api' => 'EnableBanking',
                'csv' => 'CSV-import',
                default => 'Handmatig',
            },
        ];
    }

    private function mapAccount(BankAccount $account): array
    {
        return [
            'id' => $account->key,
            'name' => $account->name,
            'bankName' => $account->bank_name,
            'iban' => $account->iban,
            'type' => $account->type === 'savings' ? 'savings' : 'checking',
            'balance' => (float) $account->balance,
            'availableBalance' => (float) $account->available_balance,
            'currency' => $account->currency ?: 'EUR',
            'lastSync' => $account->last_synced_at?->timezone('Europe/Amsterdam')->format('H:i') ?? '',
            'status' => $account->status ?: 'disconnected',
            'syncCountToday' => (int) $account->sync_count_today,
        ];
    }

    private function savingsHistory(int $year, $transactions, $goals): array
    {
        $planned = (float) $goals->sum('monthly_contribution');
        $running = (float) $goals->sum('initial_amount');
        $rows = [];

        foreach (self::MONTHS as $monthId => $monthName) {
            $prefix = sprintf('%d-%02d', $year, $this->monthNumber($monthId));
            $monthTx = $transactions->filter(fn (array $tx) => ($tx['type'] ?? '') === 'Sparen' && str_starts_with((string) $tx['date'], $prefix));
            $out = (float) $monthTx->filter(fn (array $tx) => $tx['amount'] < 0)->sum(fn (array $tx) => abs($tx['amount']));
            $in = (float) $monthTx->filter(fn (array $tx) => $tx['amount'] > 0)->sum('amount');
            $sparen = $planned > 0 ? min($out, $planned) : $out;
            $extra = max(0, $out - $sparen);
            $opening = $running;
            $running = $opening + $sparen + $extra - $in;
            $rows[] = [
                'month' => $monthName,
                'monthId' => $monthId,
                'opRekening' => round($opening, 2),
                'sparen' => round($sparen, 2),
                'extra' => round($extra, 2),
                'opgenomen' => round($in, 2),
                'totaal' => round($running, 2),
            ];
        }

        return $rows;
    }

    private function monthNumber(string $monthId): int
    {
        return array_search($monthId, array_keys(self::MONTHS), true) + 1;
    }

    private function toSparenType(?string $type): string
    {
        return match ($type) {
            'income' => 'inkomsten',
            'saving' => 'sparen',
            default => 'uitgaven',
        };
    }

    private function fromSparenType(?string $type): string
    {
        return match ($type) {
            'inkomsten', 'income' => 'income',
            'sparen', 'saving' => 'saving',
            default => 'expense',
        };
    }

    private function fromSparenTxType(?string $type): string
    {
        return match ($type) {
            'Inkomsten', 'inkomsten' => 'income',
            'Sparen', 'sparen' => 'saving',
            default => 'expense',
        };
    }

    private function fromSparenSource(?string $source): string
    {
        return match ($source) {
            'EnableBanking' => 'api',
            'CSV-import' => 'csv',
            default => 'manual',
        };
    }

    private function extractIban(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $compact = strtoupper(str_replace(' ', '', $value));
        if (preg_match('/NL[0-9]{2}[A-Z]{4}[0-9]{10}/', $compact, $matches)) {
            return $matches[0];
        }

        return null;
    }
}
