<?php

namespace App\Http\Controllers;

use App\Services\TrueLayer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrueLayerController extends Controller
{
    public function index(Request $request, TrueLayer $trueLayer): \Inertia\Response
    {
        $account = null;
        $balance = null;
        $transactions = [];
        $connected = false;
        $error = null;

        $token = (string) $request->session()->get('truelayer_access_token', '');

        if ($token !== '') {
            try {
                $accountsResponse = $trueLayer->accounts($token);
                $accounts = $accountsResponse['results'] ?? [];
                $account = $accounts[0] ?? null;

                if (is_array($account) && isset($account['account_id'])) {
                    $connected = true;
                    $balancesResponse = $trueLayer->balances($token, (string) $account['account_id']);
                    $transactionsResponse = $trueLayer->transactions($token, (string) $account['account_id']);
                    $balance = $balancesResponse['results'][0] ?? null;
                    $transactions = array_slice($transactionsResponse['results'] ?? [], 0, 25);
                }
            } catch (\Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        return Inertia::render('TrueLayerIntegration', [
            'connected' => $connected,
            'account' => $account,
            'balance' => $balance,
            'transactions' => $transactions,
            'configured' => (bool) config('services.truelayer.client_id') && (bool) config('services.truelayer.client_secret'),
            'error' => $error,
        ]);
    }

    public function redirect(Request $request, TrueLayer $trueLayer): RedirectResponse
    {
        $state = bin2hex(random_bytes(16));
        $request->session()->put('truelayer_oauth_state', $state);

        return redirect()->away($trueLayer->authUrl($state));
    }

    public function callback(Request $request, TrueLayer $trueLayer): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        if ($request->input('state') !== $request->session()->get('truelayer_oauth_state')) {
            return redirect('/truelayer')->with('error', 'Ongeldige state ontvangen van TrueLayer.');
        }

        $tokenResponse = $trueLayer->exchangeCode((string) $request->input('code'));
        $accessToken = (string) ($tokenResponse['access_token'] ?? '');

        if ($accessToken === '') {
            return redirect('/truelayer')->with('error', 'Geen access token ontvangen van TrueLayer.');
        }

        $request->session()->put('truelayer_access_token', $accessToken);

        return redirect('/truelayer');
    }
}

