<?php

namespace App\Services;

use App\Models\ImportRule;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

class ImportRuleMatcherQuery
{
    public function forRule(ImportRule $rule, bool $onlyUnassigned = true): Builder
    {
        return Transaction::query()
            ->when($onlyUnassigned, function (Builder $query) {
                $query->whereNull('category_id')->whereNull('rule_id');
            })
            ->when($rule->type === 'iban', function (Builder $query) use ($rule) {
                $query->whereNotNull('counterparty_iban')
                    ->where('counterparty_iban', 'like', '%'.$rule->match_value.'%');
            }, function (Builder $query) use ($rule) {
                $query->where('description', 'like', '%'.$rule->match_value.'%');
            });
    }
}

