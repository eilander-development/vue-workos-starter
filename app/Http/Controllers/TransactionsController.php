<?php

namespace App\Http\Controllers;

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
        $transactions = \App\Services\Transactions::list(
            searchTerm: $request->input('search') ?? '',
            type: $request->input('type') ?? ''
        );
        

        if ($request->wantsJson()) {
            return response()->json([
                'filters' => $request->only(['search', 'type']),
                'transactions' => $transactions,
            ]);
        }

        return Inertia::render('Transactions', [
            'filters' => $request->only(['search', 'type']),
            'categories' => \App\Services\Categories::list(),
            'transactions' => $transactions,
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

        return response()->json([
            'transactionId' => $transactionId,
            'categoryId' => $data['categoryId'],
            'budgetId' => $data['budgetId'],
            'type' => $data['type'],
            'icon' => $data['icon'],
            'color' => $data['color'],
        ]);
    }
}
