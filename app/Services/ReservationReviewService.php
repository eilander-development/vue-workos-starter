<?php

namespace App\Services;

use App\Models\Transaction;
use App\Support\BankTransactionIdentity;
use App\Support\TransactionAllocations;
use Illuminate\Support\Facades\DB;

class ReservationReviewService
{
    public function __construct(private TransactionLifecycle $lifecycle, private SparenStateService $state) {}

    public function review(string $key, string $search = ''): array
    {
        $reservation = Transaction::query()->forPublicKey($key)->firstOrFail();
        $this->assertReservation($reservation);
        $account = $this->account($reservation);
        // Suggestions are never evidence that two records represent one payment.
        $query = Transaction::query()->booked()->where('source_type', 'api')
            ->where('amount', $reservation->amount < 0 ? '<' : '>=', 0);
        if (trim($search) === '') {
            $query->whereBetween('date', [$reservation->date->copy()->subDays(31)->toDateString(), $reservation->date->copy()->addDays(31)->toDateString()]);
        } else {
            $query->where('description', 'like', '%'.trim($search).'%');
        }
        $candidates = $query->with(['category', 'budget', 'importRule'])->orderByDesc('date')->orderByDesc('id')->get()
            ->filter(fn ($tx) => ! $tx->is_pending && $account !== '' && $this->account($tx) === $account)->take(50)->values();

        return [
            'reservation' => $this->state->mapTransaction($reservation),
            'candidates' => $candidates->map(fn ($tx) => [
                ...$this->state->mapTransaction($tx),
                'linksCompatible' => $this->lifecycle->compatible([$reservation, $tx]),
            ])->all(),
        ];
    }

    public function resolve(string $key, array $data): array
    {
        return DB::connection('ledger')->transaction(function () use ($key, $data) {
            // Lock in a consistent order, including the chosen definitive booking.
            $query = Transaction::query()->where(function ($q) use ($key, $data) {
                $q->forPublicKey($key);
                if (! empty($data['bookedKey'])) {
                    $q->orWhere(fn ($other) => $other->forPublicKey($data['bookedKey']));
                }
            });
            $rows = $query->orderBy('id')->lockForUpdate()->get();
            $reservation = $rows->first(fn ($tx) => ($tx->key ?: 'tx-'.$tx->id) === $key);
            abort_unless($reservation, 409, 'De reservering is inmiddels verwerkt. Vernieuw het overzicht.');
            $this->assertReservation($reservation);
            abort_unless(hash_equals($reservation->revision(), $data['revision']), 409, 'De reservering is gewijzigd. Laad de beoordeling opnieuw.');
            if ($data['action'] === 'expire') {
                $this->lifecycle->archive($reservation, null, 'reservation_expired');

                return ['reservationKey' => $key, 'booked' => null];
            }

            $booked = $rows->first(fn ($tx) => ($tx->key ?: 'tx-'.$tx->id) === ($data['bookedKey'] ?? null));
            abort_unless($booked && $booked->id !== $reservation->id && ! $booked->is_pending && $booked->source_type === 'api', 422, 'Kies een definitieve bankbetaling.');
            abort_unless(hash_equals($booked->revision(), $data['bookedRevision']), 409, 'De definitieve betaling is gewijzigd. Laad de beoordeling opnieuw.');
            abort_unless($this->account($reservation) !== '' && $this->account($reservation) === $this->account($booked), 422, 'De betalingen horen niet bij dezelfde eigen rekening.');
            abort_unless(((float) $reservation->amount < 0) === ((float) $booked->amount < 0), 422, 'De betalingen hebben een andere richting.');

            $choice = $data['linkChoice'] ?? 'merge';
            if ($choice === 'merge') {
                abort_unless($this->lifecycle->compatible([$reservation, $booked]), 422, 'De koppelingen verschillen. Kies welke koppelingen en splitsingen behouden moeten blijven.');
            }
            $reservationSplit = TransactionAllocations::normalize($reservation->allocations ?: $reservation->importRule?->allocations);
            $usesReservationSplit = $choice === 'reservation' || ($choice === 'merge' && empty($booked->allocations) && $reservationSplit !== []);
            $amountChanged = number_format((float) $reservation->amount, 2, '.', '') !== number_format((float) $booked->amount, 2, '.', '');
            if ($usesReservationSplit && $reservationSplit !== [] && $amountChanged) {
                abort_unless($data['scaleSplit'] ?? false, 422, 'Het definitieve bedrag wijkt af. Bevestig de aangepaste verdeling of behoud de koppelingen van de definitieve betaling.');
            }

            $scaled = $usesReservationSplit && $reservationSplit !== [] ? TransactionAllocations::scaleToAmount($reservationSplit, (float) $booked->amount) : null;
            abort_if($scaled && (collect($scaled)->contains(fn ($row) => $row['amount'] <= 0)
                || array_sum(array_map(fn ($row) => (int) round($row['amount'] * 100), $scaled)) !== (int) round(abs((float) $booked->amount) * 100)), 422, 'Het bankbedrag is te klein voor deze splitsing. Pas de koppeling aan voordat je verdergaat.');
            $this->lifecycle->rememberReservation($booked, 'links_before_reservation_match');
            if ($choice === 'reservation') {
                $booked->fill($reservation->only(TransactionLifecycle::LINK_FIELDS));
            } elseif ($choice === 'merge') {
                $this->lifecycle->mergeLinks($booked, [$reservation]);
            }
            if ($usesReservationSplit && $reservationSplit !== []) {
                $booked->allocations = $scaled;
            }
            $booked->save();
            $this->lifecycle->archive($reservation, $booked, 'reservation_matched');

            return ['reservationKey' => $key, 'booked' => $this->state->mapTransaction($booked->fresh(['category', 'budget', 'importRule']))];
        });
    }

    private function assertReservation(Transaction $tx): void
    {
        abort_unless($tx->source_type === 'api' && $tx->is_pending, 409, 'Deze banktransactie is geen openstaande reservering meer.');
    }

    private function account(Transaction $tx): string
    {
        return BankTransactionIdentity::ownAccount($tx->bank_payload, (float) $tx->amount, $tx->account_iban);
    }
}
