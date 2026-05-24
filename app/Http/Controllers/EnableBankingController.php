<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnableBanking\ImportEnabledBankingTransactionsRequest;
use App\Services\EnableBanking;
use App\Services\EnableBankingDataService;
use App\Services\EnabledBankingTransactionImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class EnableBankingController extends Controller
{
    public function __construct(
        protected EnableBanking $client,
        protected EnableBankingDataService $dataService,
        protected EnabledBankingTransactionImporter $enabledBankingTransactionImporter,
    ) {}

    public function index()
    {
        return Inertia::render('EnableBanking', [
            'hasActiveConnection' => session()->has('eb_session_id') && ! empty(session('eb_session_id')),
        ]);
    }

    public function connect(Request $request)
    {
        try {
            $redirectUri = config('services.enablebanking.redirect_uri') ?? url('/enabled-banking/auth_redirect');
            $authSession = $this->client->initAuth($redirectUri, 'ING', 'NL');

            if (isset($authSession['generated_state'])) {
                session(['eb_oauth_state' => $authSession['generated_state']]);
            }

            return response()->json([
                'url' => $authSession['url'] ?? null,
                'access_token' => 'session_active',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Kon bankkoppeling niet starten: '.$e->getMessage()], 500);
        }
    }

    public function handleCallback(Request $request)
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $savedState = session('eb_oauth_state');

        if (! $state || ! $savedState || $state !== $savedState) {
            session()->forget('eb_oauth_state');

            return redirect()->to('/enabled-banking')->with('error', 'Beveiligingsfout: Ongeldige state.');
        }
        session()->forget('eb_oauth_state');

        if (! $code) {
            return redirect()->to('/enabled-banking')->with('error', 'Geen autorisatiecode ontvangen.');
        }

        try {
            $sessionData = $this->client->authorizeSession($code);
            $sessionId = $sessionData['session_id'] ?? null;
            $accountsList = $sessionData['accounts'] ?? [];

            if ($sessionId) {
                session(['eb_session_id' => $sessionId]);
                session(['eb_cached_accounts' => $accountsList]);

                return redirect()->to('/enabled-banking?session_id='.$sessionId);
            }

            return redirect()->to('/enabled-banking')->with('error', 'Geen geldige sessie gegenereerd.');
        } catch (\Exception $e) {
            return redirect()->to('/enabled-banking')->with('error', 'Sessie activeren mislukt: '.$e->getMessage());
        }
    }

    public function balance(Request $request)
    {
        try {
            $list = session('eb_cached_accounts');
            if (! $list && session()->has('eb_session_id')) {
                $sessionData = $this->client->getSessionData(session('eb_session_id'));
                $list = $sessionData['accounts_data'] ?? ($sessionData['accounts'] ?? []);
            }

            if (! $list) {
                return response()->json(['accounts' => []]);
            }

            return response()->json(['accounts' => $this->dataService->mapAccountBalances($list, $this->client)]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Kon saldi niet ophalen: '.$e->getMessage()], 500);
        }
    }

    public function transactions(Request $request, string $accountId)
    {
        try {
            $queryParams = $this->dataService->buildTransactionQueryParams(
                $request->query('days'),
                $request->query('date_from'),
                $request->query('from'),
                $request->query('date_to'),
                $request->query('to'),
            );

            return response()->json([
                'transactions' => $this->client->getAllTransactions($accountId, $queryParams),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Kon transacties niet ophalen: '.$e->getMessage()], 500);
        }
    }

    public function disconnect(Request $request)
    {
        try {
            session()->forget(['eb_session_id', 'eb_cached_accounts', 'eb_oauth_state']);

            return response()->json(['status' => 'disconnected']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Disconnect mislukt: '.$e->getMessage()], 500);
        }
    }

    public function importTransactions(ImportEnabledBankingTransactionsRequest $request): JsonResponse
    {
        $stats = $this->enabledBankingTransactionImporter->import($request->validated()['transactions']);

        return response()->json(['stats' => $stats]);
    }
}