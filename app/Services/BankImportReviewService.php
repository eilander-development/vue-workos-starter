<?php

namespace App\Services;

use App\Models\BankImportReview;
use App\Models\Transaction;
use App\Support\BankTransactionData;
use App\Support\BankTransactionIdentity;
use App\Support\BankTransactionStatus;
use App\Support\BankTransactionTime;
use App\Support\TransactionAllocations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BankImportReviewService
{
    private ?array $openIdentities = null;

    public function __construct(private TransactionLifecycle $lifecycle, private SparenStateService $state) {}

    public function beginImport(): void
    {
        $this->openIdentities = Schema::connection('ledger')->hasTable('bank_import_reviews')
            ? array_fill_keys(BankImportReview::query()->where('status', 'open')->pluck('identity_hash')->all(), true) : [];
    }

    public function record(array $incoming, array $existing): ?string
    {
        // During a rolling deployment the old, non-destructive conflict report still works.
        if (! Schema::connection('ledger')->hasTable('bank_import_reviews') || ! $this->validFinal($incoming)) {
            return null;
        }
        $identity = $this->incomingIdentity($incoming);
        if (! $identity || ! $existing || collect($existing)->contains(fn ($tx) => $this->transactionIdentity($tx) !== $identity)) {
            return null;
        }
        $keys = collect($existing)->map(fn ($tx) => $this->publicKey($tx))->sort()->values()->all();
        $context = collect($existing)->map(fn ($tx) => [$this->publicKey($tx), $tx->date->format('Y-m-d'), (string) $tx->amount, (bool) $tx->is_pending])->sortBy(0)->values()->all();
        $attributes = BankTransactionData::attributes($incoming);
        $hash = hash('sha256', json_encode([$identity, $attributes['date'], (int) round($attributes['amount'] * 100), $context], JSON_THROW_ON_ERROR));
        $incoming = $this->canonical($incoming);
        $review = BankImportReview::query()->createOrFirst(['content_hash' => $hash], [
            'key' => (string) Str::uuid(), 'identity_hash' => $identity, 'incoming_payload' => $incoming,
            'existing_keys' => $keys, 'seen_at' => now(),
        ]);
        if ($review->status === 'open') {
            $review->update(['seen_at' => now(), 'existing_keys' => $keys, 'incoming_payload' => $incoming]);
            $this->openIdentities[$identity] = true;
        }

        return $review->key;
    }

    public function observeConfirmed(array $incoming, Transaction $keep, bool $unambiguous): void
    {
        $identity = $this->incomingIdentity($incoming);
        if (! $identity || empty($this->openIdentities[$identity]) || ! $this->validFinal($incoming) || $keep->is_pending || $identity !== $this->transactionIdentity($keep)) {
            return;
        }
        $attributes = BankTransactionData::attributes($incoming);
        if ($keep->date->format('Y-m-d') !== $attributes['date'] || (int) round((float) $keep->amount * 100) !== (int) round($attributes['amount'] * 100)) {
            return;
        }
        foreach (BankImportReview::query()->where('identity_hash', $identity)->where('status', 'open')->get() as $review) {
            $old = BankTransactionData::attributes($review->incoming_payload);
            $same = $old['date'] === $attributes['date'] && (int) round($old['amount'] * 100) === (int) round($attributes['amount'] * 100);
            if ($same || $unambiguous) {
                $review->update(['status' => $same ? 'accepted' : 'superseded', 'resolved_key' => $this->publicKey($keep), 'resolved_at' => now()]);
            }
        }
        if (! BankImportReview::query()->where('identity_hash', $identity)->where('status', 'open')->exists()) {
            unset($this->openIdentities[$identity]);
        }
    }

    public function summary(BankImportReview $review): array
    {
        $incoming = BankTransactionData::attributes($review->incoming_payload);

        return [
            'key' => $review->key, 'status' => $review->status, 'revision' => $review->revision(),
            'date' => $incoming['date'], 'amount' => $incoming['amount'], 'description' => $incoming['description'],
            'account' => $incoming['account_iban'], 'reference' => BankTransactionIdentity::reference($review->incoming_payload),
            'seenAt' => $review->seen_at->toIso8601String(), 'resolvedKey' => $review->resolved_key,
        ];
    }

    public function details(BankImportReview $review): array
    {
        $rows = $this->current($review->identity_hash);

        return [
            ...$this->summary($review),
            'existing' => $rows->map(fn ($tx) => $this->state->mapTransaction($tx))->values()->all(),
            'linksCompatible' => $this->lifecycle->compatible($rows->all()),
            'otherVersions' => BankImportReview::query()->where('identity_hash', $review->identity_hash)->where('status', 'open')->where('id', '!=', $review->id)->count(),
        ];
    }

    public function accept(string $key, array $data): array
    {
        return DB::connection('ledger')->transaction(function () use ($key, $data) {
            $review = BankImportReview::query()->where('key', $key)->firstOrFail();
            abort_unless($review->status === 'open', 409, 'Deze bankboeking is al beoordeeld. Vernieuw de lijst.');
            $ids = $this->current($review->identity_hash)->modelKeys();
            $rows = Transaction::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->with(['category', 'budget', 'importRule'])->get();
            // Use the same transaction-before-review lock order as the importer.
            $versions = BankImportReview::query()->where('identity_hash', $review->identity_hash)->orderBy('id')->lockForUpdate()->get();
            $review = $versions->firstWhere('key', $key);
            abort_unless($review && $review->status === 'open', 409, 'Deze bankboeking is al beoordeeld. Vernieuw de lijst.');
            abort_unless(hash_equals($review->revision(), $data['revision']), 409, 'De bankversie is gewijzigd. Laad de beoordeling opnieuw.');
            abort_unless($this->validFinal($review->incoming_payload) && $this->incomingIdentity($review->incoming_payload) === $review->identity_hash, 422, 'De bankgegevens zijn niet geschikt voor definitief verwerken.');
            abort_if($rows->isEmpty(), 409, 'De oorspronkelijke registratie is niet meer actief. Vernieuw de beoordeling.');
            $expected = $data['existingRevisions'];
            abort_unless($rows->count() === count($expected), 409, 'De registraties zijn ondertussen gewijzigd. Laad de beoordeling opnieuw.');
            foreach ($rows as $tx) {
                abort_unless($this->transactionIdentity($tx) === $review->identity_hash && isset($expected[$this->publicKey($tx)]) && hash_equals($tx->revision(), $expected[$this->publicKey($tx)]), 409, 'Een registratie is gewijzigd. Laad de beoordeling opnieuw.');
            }
            $keep = $rows->first(fn ($tx) => $this->publicKey($tx) === $data['keeperKey']);
            abort_unless($keep, 422, 'Kies een bestaande registratie om te behouden.');
            $incoming = BankTransactionData::attributes($review->incoming_payload);
            abort_if($rows->contains(fn ($tx) => ((float) $tx->amount < 0) !== ($incoming['amount'] < 0)), 422, 'De betaalrichting wijkt af. Deze bankgegevens kunnen niet als dezelfde betaling worden verwerkt.');

            $source = $data['linkSource'] === 'merge' ? null : $rows->first(fn ($tx) => $this->publicKey($tx) === $data['linkSource']);
            abort_unless($source || $data['linkSource'] === 'merge', 422, 'Kies van welke registratie de koppelingen behouden blijven.');
            if (! $source) {
                abort_unless($this->lifecycle->compatible($rows->all()), 422, 'De koppelingen verschillen. Kies bewust welke je wilt behouden.');
            }
            $ordered = $source ? collect([$source]) : collect([$keep, ...$rows->filter(fn ($tx) => $tx->id !== $keep->id)->all()]);
            $splitSource = $ordered->first(fn ($tx) => $this->split($tx) !== []);
            $split = $splitSource ? $this->split($splitSource) : [];
            $splitTotal = array_sum(array_map(fn ($row) => (int) round($row['amount'] * 100), $split));
            abort_if($split !== [] && $splitTotal !== (int) round(abs($incoming['amount']) * 100) && ! ($data['scaleSplit'] ?? false), 422, 'Het bankbedrag wijkt af van de splitsing. Bevestig eerst de aangepaste verdeling.');
            $scaled = $split !== [] ? TransactionAllocations::scaleToAmount($split, $incoming['amount']) : null;
            abort_if($split !== [] && (count($scaled) !== count($split) || collect($scaled)->contains(fn ($row) => $row['amount'] <= 0)
                || array_sum(array_map(fn ($row) => (int) round($row['amount'] * 100), $scaled)) !== (int) round(abs($incoming['amount']) * 100)), 422, 'Het bankbedrag is te klein voor deze splitsing. Pas eerst de koppeling aan en laad de beoordeling opnieuw.');

            $this->lifecycle->rememberReservation($keep, 'bank_booking_before_acceptance');
            if ($source) {
                $keep->fill($source->only(TransactionLifecycle::LINK_FIELDS));
            } else {
                $this->lifecycle->mergeLinks($keep, $rows->all());
            }
            $removed = [];
            foreach ($rows as $other) {
                if ($other->id === $keep->id) {
                    continue;
                }
                $removed[] = $this->publicKey($other);
                $this->lifecycle->archive($other, $keep, 'bank_conflict_replaced');
            }
            $keep->fill([...$incoming, 'allocations' => $scaled]);
            $keep->save();
            $review->update(['status' => 'accepted', 'resolved_key' => $this->publicKey($keep), 'resolved_at' => now()]);
            BankImportReview::query()->where('identity_hash', $review->identity_hash)->where('status', 'open')->where('id', '!=', $review->id)
                ->update(['status' => 'superseded', 'resolved_key' => $this->publicKey($keep), 'resolved_at' => now()]);

            return ['booked' => $this->state->mapTransaction($keep->fresh(['category', 'budget', 'importRule'])), 'removedKeys' => $removed];
        });
    }

    private function current(string $identity)
    {
        return Transaction::query()->where('source_type', 'api')->with(['category', 'budget', 'importRule'])->orderBy('id')->get()
            ->filter(fn ($tx) => $this->transactionIdentity($tx) === $identity)->values();
    }

    private function split(Transaction $tx): array
    {
        return TransactionAllocations::scaleToAmount($tx->allocations ?: $tx->importRule?->allocations, (float) $tx->amount);
    }

    private function validFinal(array $row): bool
    {
        return ! BankTransactionStatus::isPending($row) && is_numeric($row['amount'] ?? null) && is_finite((float) $row['amount'])
            && abs((float) $row['amount']) <= 9999999999999.99 && BankTransactionTime::bookingDate($row, fallbackToToday: false) !== '' && $this->incomingIdentity($row) !== null;
    }

    private function incomingIdentity(array $row): ?string
    {
        $reference = BankTransactionIdentity::reference($row);
        $account = BankTransactionIdentity::ownAccount($row, (float) ($row['amount'] ?? 0), $row['account_iban'] ?? null);

        return $reference && $account !== '' ? hash('sha256', json_encode([$account, $reference])) : null;
    }

    private function transactionIdentity(Transaction $tx): ?string
    {
        return $this->incomingIdentity([...($tx->bank_payload ?? []), 'account_iban' => $tx->account_iban, 'amount' => (float) $tx->amount]);
    }

    private function publicKey(Transaction $tx): string
    {
        return $tx->key ?: 'tx-'.$tx->id;
    }

    private function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($part) => is_array($part) ? $this->canonical($part) : $part, $value);
    }
}
