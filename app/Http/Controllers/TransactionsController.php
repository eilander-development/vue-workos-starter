<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionsController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $pageNumber = $request->integer('page', 1);
        $transactions = \App\Services\Transactions::list(
            searchTerm: $request->input('search') ?? '',
            type: $request->input('type') ?? '',
            perPage: 100,
            page: $pageNumber,
        );

        $payload = [
            'current_page' => $transactions->currentPage(),
            'last_page' => $transactions->lastPage(),
            'per_page' => $transactions->perPage(),
            'total' => $transactions->total(),
            'from' => $transactions->firstItem(),
            'to' => $transactions->lastItem(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'filters' => $request->only(['search', 'type']),
                'transactions' => $transactions->items(),
                'pagination' => $payload,
            ]);
        }

        return Inertia::render('Transactions', [
            'filters' => $request->only(['search', 'type']),
            'categories' => \App\Services\Categories::list(),
            'transactions' => $transactions->items(),
            'pagination' => $payload,
        ]);
    }

    public function assign(Request $request, int $transactionId): JsonResponse
    {
        $data = $request->validate([
            'categoryId' => ['required', 'integer'],
            'budgetId' => ['required', 'integer'],
            'type' => ['required', 'string', 'in:expense,income,saving'],
            'icon' => ['required', 'string'],
            'color' => ['required', 'string'],
        ]);

        $repository = new TransactionRepository();
        $transaction = $repository->saveTransaction($transactionId, [
            'category_id' => $data['categoryId'],
            'budget_id' => $data['budgetId'],
            'type' => $data['type'],
            'icon' => $data['icon'],
            'color' => $data['color'],
        ]);

        return response()->json([
            'transactionId' => $transaction->id,
            'categoryId' => $transaction->category_id,
            'budgetId' => $transaction->budget_id,
            'type' => $transaction->type,
            'icon' => $transaction->icon,
            'color' => $transaction->color,
        ]);
    }

    public function destroy(Transaction $transaction): JsonResponse
    {
        $transaction->delete();

        return response()->json([], 204);
    }
}
