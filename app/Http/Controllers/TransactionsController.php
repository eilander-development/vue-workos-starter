<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\TransactionRepositoryInterface;
use App\Http\Requests\Transactions\AssignTransactionRequest;
use App\Models\Transaction;
use App\Services\Categories;
use App\Services\Transactions;
use App\Support\PaginationData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionsController extends Controller
{
    public function __construct(
        protected TransactionRepositoryInterface $transactionRepository,
        protected Categories $categoriesService,
        protected Transactions $transactionsService,
    ) {}

    /**
     * Show the user's profile settings page.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $pageNumber = $request->integer('page', 1);
        $transactions = $this->transactionsService->list(
            searchTerm: $request->input('search') ?? '',
            type: $request->input('type') ?? '',
            perPage: 100,
            page: $pageNumber,
        );

        $payload = PaginationData::fromPaginator($transactions);

        if ($request->wantsJson()) {
            return response()->json([
                'filters' => $request->only(['search', 'type']),
                'transactions' => $transactions->items(),
                'pagination' => $payload,
            ]);
        }

        return Inertia::render('Transactions', [
            'filters' => $request->only(['search', 'type']),
            'categories' => $this->categoriesService->list(),
            'transactions' => $transactions->items(),
            'pagination' => $payload,
        ]);
    }

    public function assign(AssignTransactionRequest $request, int $transactionId): JsonResponse
    {
        $data = $request->validated();

        $transaction = $this->transactionRepository->saveTransaction($transactionId, [
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
