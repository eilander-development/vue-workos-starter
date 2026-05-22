<?php

namespace App\Services;

use App\Models\ImportRule;

class TransactionRuleEnricher
{
    public function enrich(array $payload, ImportRule $rule): array
    {
        $category = $rule->category;

        $payload['category_id'] = $rule->category_id;
        $payload['budget_id'] = $rule->budget_id;
        $payload['rule_id'] = $rule->id;
        $payload['type'] = $category?->type ?? ($payload['type'] ?? null);
        $payload['icon'] = $category?->icon;
        $payload['color'] = $category?->color;

        return $payload;
    }
}
