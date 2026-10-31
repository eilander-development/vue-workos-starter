<?php

namespace App\Services;

use App\Models\Budget;

class ForecastExpensePolicy
{
    public function cell(Budget $budget, array $resolved, string $month): array
    {
        $cell = ['amount' => $resolved['amount'], 'scheduled' => $resolved['scheduled']];
        if ($budget->category?->type !== 'expense') {
            return $cell;
        }

        $mode = $budget->forecast_mode ?? 'auto';
        $deferred = preg_match('/\bklarna\b|\bcredit\s*card\b/iu', $budget->name) === 1;
        $extra = $mode === 'extra' || ($mode === 'auto' && ($resolved['entries'] !== [] || $deferred));
        if (! $extra) {
            return $cell;
        }

        // Extra costs never silently become an annual recurring amount.
        $cell = ['amount' => 0, 'scheduled' => false];
        if ($resolved['amount'] > 0) {
            $cell['excludedCost'] = [
                'kind' => $resolved['sourceMonth'] === $month || $resolved['datedChange'] ? 'planned' : 'historical',
                'amount' => $resolved['amount'], 'sourceMonth' => $resolved['sourceMonth'],
                'reason' => $mode === 'extra' ? 'marked_extra' : ($deferred ? 'deferred' : 'month_entries'),
                'entries' => $resolved['entries'],
            ];
        }

        return $cell;
    }
}
