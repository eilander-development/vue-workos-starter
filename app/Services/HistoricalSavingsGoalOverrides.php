<?php

namespace App\Services;

use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Support\IngSavingsTransfer;
use Illuminate\Support\Facades\Schema;

/**
 * One-time data fix: before the C/L split, three €100 deposits to L were meant for benzine/C.
 * Matching itself never uses these dates — only the persisted savings_goal_key.
 */
class HistoricalSavingsGoalOverrides
{
    public const SOURCE_REF = 'L13628386';

    /** @var list<string> */
    public const DATES = ['2026-06-24', '2026-07-24', '2026-08-24'];

    public const AMOUNT = 100.0;

    public function __construct(
        protected TransactionClassifier $classifier,
    ) {}

    public function apply(): int
    {
        if (! Schema::hasColumn('transactions', 'savings_goal_key')) {
            return 0;
        }

        $goal = $this->benzinePot();
        if (! $goal) {
            return 0;
        }

        $updated = 0;
        $candidates = Transaction::query()
            ->where(function ($query) {
                $query->whereNull('savings_goal_key')->orWhere('savings_goal_key', '');
            })
            ->whereIn('date', self::DATES)
            ->get();

        foreach ($candidates as $transaction) {
            if (! $this->isCandidate($transaction)) {
                continue;
            }

            $transaction->savings_goal_key = $goal->key;
            $transaction->save();
            $this->classifier->classifyCollection(collect([$transaction->fresh()]));
            $updated++;
        }

        return $updated;
    }

    public function isCandidate(Transaction $transaction): bool
    {
        if (abs(abs((float) $transaction->amount) - self::AMOUNT) > 0.01) {
            return false;
        }

        $parsed = IngSavingsTransfer::parseDestination((string) $transaction->description);
        if (! $parsed || $parsed['isSpaarpot'] || ($parsed['ref'] ?? '') !== self::SOURCE_REF) {
            return false;
        }

        $date = optional($transaction->date)->toDateString();

        return in_array($date, self::DATES, true);
    }

    private function benzinePot(): ?SavingsGoal
    {
        return SavingsGoal::query()
            ->where('kind', 'pot')
            ->get()
            ->first(function (SavingsGoal $goal) {
                $haystack = mb_strtolower(trim($goal->name.' '.$goal->account_iban));

                return str_contains($haystack, 'c13134173')
                    || str_contains($haystack, 'benzine');
            });
    }
}
