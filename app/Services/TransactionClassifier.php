<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\ImportRule;
use App\Models\SavingsGoal;
use Illuminate\Support\Collection;

class TransactionClassifier
{
    /**
     * @return array{type: string, category_id: ?int, budget_id: ?int, rule_id: ?int, category_group: ?string, budget_key: ?string}
     */
    public function classify(string $description, ?string $counterpartyIban, ?string $counterpartyName = null): array
    {
        $haystack = mb_strtolower(trim($description.' '.($counterpartyName ?? '').' '.($counterpartyIban ?? '')));
        $ibanHaystack = strtoupper(preg_replace('/\s+/', '', $description.' '.($counterpartyIban ?? '').' '.($counterpartyName ?? '')) ?? '');

        foreach (SavingsGoal::query()->get() as $goal) {
            $cleanIban = strtoupper(preg_replace('/\s+/', '', $goal->account_iban) ?? '');
            if ($cleanIban !== '' && str_contains($ibanHaystack, $cleanIban)) {
                $budget = Budget::query()->where('key', $goal->budget_key)->first();

                return [
                    'type' => 'saving',
                    'category_id' => $budget?->category_id,
                    'budget_id' => $budget?->id,
                    'rule_id' => null,
                    'category_group' => $budget?->category?->name,
                    'budget_key' => $budget?->key,
                ];
            }
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
            );

            $transaction->fill([
                'type' => $result['type'] ?? $transaction->type,
                'category_id' => $result['category_id'],
                'budget_id' => $result['budget_id'],
                'rule_id' => $result['rule_id'],
            ]);
            $transaction->save();
        }
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
}
