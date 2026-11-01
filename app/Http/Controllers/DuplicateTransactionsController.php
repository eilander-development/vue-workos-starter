<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\TransactionLifecycle;
use App\Support\BankTransactionIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DuplicateTransactionsController extends Controller
{
    public function index(): JsonResponse
    {
        $groups = Transaction::query()->where('source_type', 'api')->with(['category', 'budget'])->orderBy('id')->get()
            ->filter(fn ($tx) => BankTransactionIdentity::key($tx) !== null)
            ->groupBy(fn ($tx) => BankTransactionIdentity::key($tx))
            ->filter(fn ($rows) => $rows->count() > 1)
            ->map(fn ($rows) => $rows->sortByDesc(fn ($tx) => (int) (bool) $tx->budget_id))
            ->map(fn ($rows) => [
                'reference' => BankTransactionIdentity::reference($rows->first()->bank_payload),
                'rows' => $rows->map(fn ($tx) => [
                    'id' => $tx->id,
                    'key' => $tx->key ?: 'tx-'.$tx->id,
                    'date' => $tx->date->format('Y-m-d'),
                    'description' => $tx->description,
                    'amount' => (float) $tx->amount,
                    'category' => $tx->category?->name,
                    'budget' => $tx->budget?->name,
                    'account' => BankTransactionIdentity::ownAccount($tx->bank_payload, (float) $tx->amount, $tx->account_iban),
                    'importedAt' => $tx->created_at?->timezone('Europe/Amsterdam')->format('d-m-Y H:i:s'),
                ])->values(),
            ])->values();

        return response()->json(['groups' => $groups]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'keepId' => ['required', 'integer'],
            'removeId' => ['required', 'integer', 'different:keepId'],
        ]);

        return DB::connection('ledger')->transaction(function () use ($data) {
            $rows = Transaction::query()->whereIn('id', [$data['keepId'], $data['removeId']])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $keep = $rows->get($data['keepId']);
            $remove = $rows->get($data['removeId']);
            abort_unless($keep && $remove, 409, 'De transacties zijn gewijzigd. Vernieuw het overzicht.');
            $identity = BankTransactionIdentity::key($keep);
            abort_unless($identity !== null && $identity === BankTransactionIdentity::key($remove), 422, 'Deze rijen zijn geen bevestigde dubbele banktransactie.');
            $lifecycle = new TransactionLifecycle;
            abort_unless($lifecycle->compatible([$keep, $remove]), 422, 'De koppelingen of splitsingen spreken elkaar tegen. Los dit eerst op; er is niets gewijzigd.');
            $lifecycle->mergeLinks($keep, [$remove]);
            $deletedKey = $remove->key ?: 'tx-'.$remove->id;
            $lifecycle->archive($remove, $keep, 'confirmed_duplicate');

            return response()->json(['deletedKey' => $deletedKey, 'archivedKey' => $deletedKey]);
        });
    }
}
