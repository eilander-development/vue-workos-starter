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
        $transactions = [
            [
                'id' => 1,
                'amount' => -23.30,
                'categoryId' => 1,
                'budgetId' => 1,
                'description' => 'Pizza Palace',
                'date' => now()->format('d-m-Y'),
            ],
            [
                'id' => 2,
                'amount' => +53.30,
                'categoryId' => 2,
                'budgetId' => 1,
                'description' => 'Belastingdienst',
                'date' => now()->format('d-m-Y'),
            ]
        ];
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
}
