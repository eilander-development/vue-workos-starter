<?php

namespace App\Http\Controllers;

use App\Models\PlaidConnection;
use App\Models\PlaidSetting;
use App\Services\Plaid;
use App\Services\PlaidImporter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PlaidController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $connection = $request->user()->plaidConnections()->latest()->first();
        $settings = PlaidSetting::active();

        return Inertia::render('PlaidIntegration', [
            'plaidConnection' => $connection ? $connection->only(['id', 'item_id', 'institution_name']) : null,
            'plaidSettings' => $settings ? [
                'client_id' => $settings->client_id,
                'environment' => $settings->environment,
                'webhook_url' => $settings->webhook_url,
            ] : null,
        ]);
    }

    public function linkToken(Request $request, Plaid $plaid): JsonResponse
    {
        $linkToken = $plaid->createLinkToken($request->user());

        return response()->json(['link_token' => $linkToken]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'string'],
            'secret' => ['required', 'string'],
            'environment' => ['required', 'in:sandbox,development,production'],
            'webhook_url' => ['nullable', 'url'],
        ]);

        $settings = PlaidSetting::query()->updateOrCreate(
            [],
            $data,
        );

        return response()->json([
            'settings' => [
                'client_id' => $settings->client_id,
                'environment' => $settings->environment,
                'webhook_url' => $settings->webhook_url,
            ],
            'secret_saved' => true,
        ]);
    }

    public function connect(Request $request, Plaid $plaid, PlaidImporter $importer): JsonResponse
    {
        $request->validate([
            'public_token' => ['required', 'string'],
        ]);

        $response = $plaid->exchangePublicToken($request->input('public_token'));
        $accessToken = $response['access_token'] ?? null;
        $itemId = $response['item_id'] ?? null;

        if (! $accessToken || ! $itemId) {
            return response()->json(['message' => 'Ongeldige Plaid-respons'], 422);
        }

        $connection = $request->user()->plaidConnections()->updateOrCreate(
            ['item_id' => $itemId],
            ['access_token' => $accessToken],
        );

        try {
            $summary = $this->importRecentTransactions($plaid, $connection, $importer);
        } catch (\Throwable $exception) {
            Log::error('Plaid transaction import failed', ['exception' => $exception]);
            return response()->json(['message' => 'Bankverbinding is gemaakt, maar transacties konden niet worden opgehaald.'], 500);
        }

        return response()->json([
            'connection' => [
                'id' => $connection->id,
                'item_id' => $connection->item_id,
            ],
            'summary' => $summary,
        ]);
    }

    public function refresh(Request $request, Plaid $plaid, PlaidImporter $importer): JsonResponse
    {
        $connection = $request->user()->plaidConnections()->latest()->first();

        if (! $connection) {
            return response()->json(['message' => 'Geen Plaid-verbinding gevonden.'], 404);
        }

        try {
            $summary = $this->importRecentTransactions($plaid, $connection, $importer);
        } catch (\Throwable $exception) {
            Log::error('Plaid refresh failed', ['exception' => $exception]);
            return response()->json(['message' => 'Kon transacties niet verversen.'], 500);
        }

        return response()->json(['summary' => $summary]);
    }

    protected function importRecentTransactions(Plaid $plaid, PlaidConnection $connection, PlaidImporter $importer): array
    {
        $endDate = now()->format('Y-m-d');
        $startDate = now()->subDays(90)->format('Y-m-d');

        $transactions = [];
        $offset = 0;
        $total = null;

        do {
            $response = $plaid->fetchTransactions($connection->access_token, $startDate, $endDate, 100, $offset);
            $paginated = $response['transactions'] ?? [];
            $total = $response['total_transactions'] ?? count($paginated);
            $transactions = array_merge($transactions, $paginated);
            $offset += count($paginated);
        } while ($total !== null && $offset < $total);

        return $importer->import($transactions);
    }
}
