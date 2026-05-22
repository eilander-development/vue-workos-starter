<?php

namespace App\Services;

use App\Models\ImportRule;
use Illuminate\Support\Collection;

class ImportRuleMatcher
{
    public function findMatch(Collection $rules, ?string $counterpartyIban, string $description): ?ImportRule
    {
        return $rules->first(function (ImportRule $rule) use ($counterpartyIban, $description) {
            if ($rule->type === 'iban') {
                return $counterpartyIban && str_contains(mb_strtolower($counterpartyIban), mb_strtolower($rule->match_value));
            }

            return str_contains(mb_strtolower($description), mb_strtolower($rule->match_value));
        });
    }
}
