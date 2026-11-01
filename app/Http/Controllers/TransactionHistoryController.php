<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\SparenStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionHistoryController extends Controller
{
    public function index(Request $request, SparenStateService $state): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:200'], 'transactionKey' => ['nullable', 'string', 'max:255']]);
        $query = Transaction::onlyHistory()->with(['category', 'budget', 'importRule']);
        if (! empty($data['search'])) {
            $query->where('description', 'like', '%'.$data['search'].'%');
        }
        if (! empty($data['transactionKey'])) {
            $key = $data['transactionKey'];
            $query->where(fn ($q) => $q->where('superseded_by_key', $key)->orWhere('key', $key));
        }

        return response()->json($query->orderByDesc('archived_at')->orderByDesc('id')->paginate(50)->through(fn ($tx) => $state->mapTransaction($tx)));
    }
}
