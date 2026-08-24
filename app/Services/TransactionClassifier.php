<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Category;
use App\Models\ImportRule;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class TransactionClassifier
{
    /**
     * @return array{type: string, category_id: ?int, budget_id: ?int, rule_id: ?int, category_group: ?string, budget_key: ?string}
     */
    public function classify(string $description, ?string $counterpartyIban, ?string $counterpartyName = null, ?float $amount = null): array
    {
        $haystack = mb_strtolower(trim($description.' '.($counterpartyName ?? '').' '.($counterpartyIban ?? '')));
        $ibanHaystack = strtoupper(preg_replace('/\s+/', '', $description.' '.($counterpartyIban ?? '').' '.($counterpartyName ?? '')) ?? '');

        foreach (SavingsGoal::query()->where('kind', 'pot')->get() as $goal) {
            if (! $this->matchesSavingsTransfer($description, $goal, $haystack, $ibanHaystack)) {
                continue;
            }

            $spaarCategory = Category::query()->where('type', 'saving')->orderBy('id')->first();
            $direction = $this->potTransferDirection($description);

            return [
                'type' => 'saving',
                'category_id' => $spaarCategory?->id,
                'budget_id' => null,
                'rule_id' => null,
                'category_group' => $spaarCategory?->name ?? 'Spaargeld',
                'budget_key' => null,
                'link_excluded' => true,
                'link_exclusion_reason' => $direction === 'naar'
                    ? 'Pot-storting ('.$goal->name.') — apart bijgehouden in potje, niet koppelen aan rubriek'
                    : 'Pot-opname ('.$goal->name.') — verrekening in potje, niet koppelen aan rubriek',
            ];
        }

        foreach (SavingsGoal::query()->where('kind', '!=', 'pot')->get() as $goal) {
            if (! $this->matchesSavingsTransfer($description, $goal, $haystack, $ibanHaystack)) {
                continue;
            }

            $budget = Budget::query()->where('key', $goal->budgetKeys()[0] ?? $goal->budget_key)->first();
            $spaarCategory = Category::query()->where('type', 'saving')->orderBy('id')->first();

            return [
                'type' => 'saving',
                'category_id' => $budget?->category_id ?? $spaarCategory?->id,
                'budget_id' => $budget?->id,
                'rule_id' => null,
                'category_group' => $budget?->category?->name ?? $spaarCategory?->name ?? 'Spaargeld',
                'budget_key' => $budget?->key,
                'link_excluded' => false,
                'link_exclusion_reason' => null,
            ];
        }

        $rules = ImportRule::query()->with(['category', 'budget'])->where('is_active', true)->orderBy('id')->get();

        foreach ($rules as $rule) {
            if ($this->ruleMatches($rule, $description, $counterpartyIban, $counterpartyName, $haystack)) {
                $categoryType = $rule->category?->type;

                return [
                    'type' => $categoryType === 'income' ? 'income' : ($categoryType === 'saving' ? 'saving' : 'expense'),
                    'category_id' => $rule->category_id,
                    'budget_id' => $rule->budget_id,
                    'rule_id' => $rule->id,
                    'category_group' => $rule->category?->name,
                    'budget_key' => $rule->budget?->key,
                ];
            }
        }

        return [
            'type' => null,
            'category_id' => null,
            'budget_id' => null,
            'rule_id' => null,
            'category_group' => 'Ongecategoriseerd',
            'budget_key' => null,
        ];
    }

    public function classifyCollection(Collection $transactions): void
    {
        foreach ($transactions as $transaction) {
            $result = $this->classify(
                (string) $transaction->description,
                $transaction->counterparty_iban,
                $transaction->counterparty_name,
                (float) $transaction->amount,
            );

            $transaction->fill([
                'type' => $result['type'] ?? $transaction->type,
                'category_id' => $result['category_id'],
                'budget_id' => $result['budget_id'],
                'rule_id' => $result['rule_id'],
                'link_excluded' => (bool) ($result['link_excluded'] ?? false),
                'link_exclusion_reason' => $result['link_exclusion_reason'] ?? null,
            ]);
            $transaction->save();
        }
    }

    /** Herclassificeer bestaande Naar/Van-overboekingen voor potjes. */
    public function reclassifyPotDeposits(): int
    {
        $updated = 0;
        $spaarCategory = Category::query()->where('type', 'saving')->orderBy('id')->first();

        foreach (SavingsGoal::query()->where('kind', 'pot')->get() as $goal) {
            $keyword = mb_strtolower(trim($goal->name));
            if (mb_strlen($keyword) < 3) {
                continue;
            }

            $transactions = Transaction::query()
                ->whereRaw('LOWER(description) LIKE ?', ['%'.$keyword.'%'])
                ->get();

            foreach ($transactions as $transaction) {
                $direction = $this->potTransferDirection((string) $transaction->description);
                if ($direction === null) {
                    continue;
                }

                $transaction->fill([
                    'type' => 'saving',
                    'category_id' => $spaarCategory?->id,
                    'budget_id' => null,
                    'rule_id' => null,
                    'link_excluded' => true,
                    'link_exclusion_reason' => $direction === 'naar'
                        ? 'Pot-storting ('.$goal->name.') — apart bijgehouden in potje, niet koppelen aan rubriek'
                        : 'Pot-opname ('.$goal->name.') — verrekening in potje, niet koppelen aan rubriek',
                ]);
                $transaction->save();
                $updated++;
            }
        }

        return $updated;
    }

    private function ruleMatches(ImportRule $rule, string $description, ?string $iban, ?string $counterparty, string $haystack): bool
    {
        $keyword = mb_strtolower((string) $rule->match_value);
        if ($keyword === '') {
            return false;
        }

        $inDescription = str_contains(mb_strtolower($description), $keyword);
        $inCounterparty = str_contains(mb_strtolower(($counterparty ?? '').' '.($iban ?? '')), $keyword);
        $field = $rule->match_field ?: ($rule->type === 'iban' ? 'counterparty' : 'description');

        return match ($field) {
            'counterparty' => $inCounterparty,
            'both' => $inDescription || $inCounterparty,
            default => $inDescription || ($rule->type === 'iban' && $iban && str_contains(mb_strtolower($iban), $keyword)),
        };
    }

    private function matchesSavingsTransfer(
        string $description,
        SavingsGoal $goal,
        string $haystack,
        string $ibanHaystack,
    ): bool {
        if ($this->potTransferDirection($description) === null) {
            return false;
        }

        $keyword = mb_strtolower(trim($goal->name));
        if (mb_strlen($keyword) >= 3 && str_contains($haystack, $keyword)) {
            return true;
        }

        $cleanIban = strtoupper(preg_replace('/\s+/', '', $goal->account_iban) ?? '');

        return $cleanIban !== '' && str_contains($ibanHaystack, $cleanIban);
    }

    private function potTransferDirection(string $description): ?string
    {
        $normalized = mb_strtolower(trim($description));

        if (preg_match('/^naar\s/u', $normalized) || preg_match('/\snaar\s/u', $normalized)) {
            return 'naar';
        }

        if (preg_match('/^van\s/u', $normalized) || preg_match('/\svan\s/u', $normalized)) {
            return 'van';
        }

        return null;
    }
}
