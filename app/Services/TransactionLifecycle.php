<?php

namespace App\Services;

use App\Models\Transaction;
use App\Support\BankTransactionIdentity;
use App\Support\TransactionAllocations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionLifecycle
{
    public const LINK_FIELDS = ['type', 'category_id', 'budget_id', 'rule_id', 'savings_goal_key', 'allocations', 'link_excluded', 'link_exclusion_reason'];

    /** Missing links can be transferred; conflicting links require human review. */
    public function compatible(array $rows): bool
    {
        $excluded = collect($rows)->contains(fn ($tx) => (bool) $tx->link_excluded);
        if ($excluded && collect($rows)->contains(fn ($tx) => ! $tx->link_excluded && ($tx->budget_id || $tx->rule_id || ! empty($tx->allocations)))) {
            return false;
        }
        foreach (self::LINK_FIELDS as $field) {
            $values = array_filter(array_map(fn ($tx) => $this->value($tx, $field), $rows), fn ($value) => $value !== null);
            if (count(array_unique($values, SORT_REGULAR)) > 1) {
                return false;
            }
        }

        return true;
    }

    private function value(Transaction $tx, string $field): mixed
    {
        $value = $tx->$field;
        if (empty($value)) {
            return null;
        }
        if ($field === 'allocations') {
            $value = collect($value)->sortBy('budgetItemId')->values()->all();
        }

        return $value;
    }

    public function mergeLinks(Transaction $keep, array $rows): void
    {
        foreach (self::LINK_FIELDS as $field) {
            if (! empty($keep->$field)) {
                continue;
            }
            foreach ($rows as $row) {
                if (! empty($row->$field)) {
                    $keep->$field = $row->$field;
                    break;
                }
            }
        }
        $keep->save();
    }

    public function archive(Transaction $tx, ?Transaction $keep, string $reason): void
    {
        $this->freezeDetails($tx);
        $tx->forceFill([
            'archived_at' => now(), 'archive_reason' => $reason,
            'superseded_by_key' => $keep ? ($keep->key ?: 'tx-'.$keep->id) : null,
            'source_hash' => null,
        ])->save();
    }

    public function rememberReservation(Transaction $tx, string $reason = 'reservation_finalized'): void
    {
        $copy = $tx->replicate();
        $copy->setRelations($tx->getRelations());
        $this->freezeDetails($copy);
        $copy->key = 'history-'.Str::uuid();
        $copy->source_hash = null;
        $copy->archived_at = now();
        $copy->archive_reason = $reason;
        $copy->superseded_by_key = $tx->key ?: 'tx-'.$tx->id;
        $copy->lifecycle_metadata = [...$copy->lifecycle_metadata, 'originalId' => $tx->id];
        $copy->created_at = $tx->created_at;
        $copy->save();
    }

    private function freezeDetails(Transaction $tx): void
    {
        $tx->loadMissing(['category', 'budget', 'importRule']);
        $tx->lifecycle_metadata = [
            'originalSourceHash' => $tx->source_hash,
            'categoryName' => $tx->category?->name,
            'budgetName' => $tx->budget?->name,
        ];
        if (empty($tx->allocations) && ! empty($tx->importRule?->allocations)) {
            $tx->allocations = TransactionAllocations::scaleToAmount($tx->importRule->allocations, (float) $tx->amount);
        }
    }

    /** Reconcile only proven copies; a dry run reports exactly the same eligibility. */
    public function reconcileStored(bool $apply = false): array
    {
        $groups = Transaction::query()->where('source_type', 'api')->orderBy('id')->get()->groupBy(function ($tx) {
            $reference = BankTransactionIdentity::reference($tx->bank_payload);
            $account = BankTransactionIdentity::ownAccount($tx->bank_payload, (float) $tx->amount, $tx->account_iban);

            return $reference && $account !== '' ? json_encode([$account, $reference]) : 'unknown-'.$tx->id;
        })->filter(fn ($rows) => $rows->count() > 1);
        $result = ['groups' => $groups->count(), 'eligible' => 0, 'archived' => 0, 'review' => 0];
        foreach ($groups as $group) {
            DB::connection('ledger')->transaction(function () use ($group, $apply, &$result) {
                $rows = Transaction::query()->whereIn('id', $group->modelKeys())->orderBy('id')->lockForUpdate()->get();
                $booked = $rows->filter(fn ($tx) => ! $tx->is_pending);
                if ($rows->count() < 2) {
                    return;
                }
                if ($booked->isEmpty() || $rows->map(fn ($tx) => number_format((float) $tx->amount, 2, '.', ''))->unique()->count() !== 1
                    || $booked->map(fn ($tx) => $tx->date->format('Y-m-d'))->unique()->count() !== 1 || ! $this->compatible($rows->all())) {
                    $result['review']++;

                    return;
                }
                $result['eligible'] += $rows->count() - 1;
                if (! $apply) {
                    return;
                }
                $keep = $booked->first();
                $this->mergeLinks($keep, $rows->all());
                foreach ($rows as $tx) {
                    if ($tx->id === $keep->id) {
                        continue;
                    }
                    $this->archive($tx, $keep, $tx->is_pending ? 'reservation_replaced' : 'confirmed_duplicate');
                    $result['archived']++;
                }
            });
        }

        return $result;
    }
}
