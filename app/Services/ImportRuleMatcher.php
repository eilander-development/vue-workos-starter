<?php

namespace App\Services;

use App\Models\ImportRule;
use Illuminate\Support\Collection;

class ImportRuleMatcher
{
    public function findMatch(
        Collection $rules,
        ?string $counterpartyIban,
        string $description,
        ?float $amount = null,
    ): ?ImportRule {
        return $rules->first(function (ImportRule $rule) use ($counterpartyIban, $description, $amount) {
            $type = $rule->category?->type;
            if ($amount !== null) {
                if ($type === 'income' && $amount <= 0) {
                    return false;
                }
                if ($type === 'expense' && $amount >= 0) {
                    return false;
                }
            }

            if ($rule->type === 'iban') {
                return $counterpartyIban && str_contains(mb_strtolower($counterpartyIban), mb_strtolower($rule->match_value));
            }

            return str_contains(mb_strtolower($description), mb_strtolower($rule->match_value));
        });
    }
}
