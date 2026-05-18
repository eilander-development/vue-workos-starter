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
}
