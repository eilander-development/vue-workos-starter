<?php

namespace App\Http\Controllers;

use App\Services\GoCardless;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GoCardlessController extends Controller
{
    public function index(Request $request, GoCardless $goCardless): \Inertia\Response
    {
        $merchant = null;
        $configured = true;

        try {
            $merchant = $goCardless->fetchMerchant();
        } catch (\Throwable $exception) {
            $configured = $exception instanceof \RuntimeException && str_contains($exception->getMessage(), 'ontbreekt') === false;
        }

        $connection = $request->user()->plaidConnections()->latest()->first();

        return Inertia::render('GoCardlessIntegration', [
            'gocardlessMerchant' => $merchant,
            'gocardlessConfigured' => (bool) config('services.gocardless.api_key'),
            'plaidConnection' => $connection ? $connection->only(['id', 'item_id', 'institution_name']) : null,
        ]);
    }

    public function connect(GoCardless $goCardless): JsonResponse
    {
        try {
            $merchant = $goCardless->fetchMerchant();

            return response()->json(['merchant' => $merchant]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
